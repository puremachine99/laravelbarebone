<?php

use App\Models\ErrorLog;
use App\Services\ErrorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the debug login page', function () {
    $this->get(route('dev.errors.index'))
        ->assertRedirect(route('dev.login'));
});

test('wrong password is rejected', function () {
    $this->post(route('dev.login.store'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    $this->get(route('dev.errors.index'))
        ->assertRedirect(route('dev.login'));
});

test('correct password grants access to the debug panel', function () {
    $this->post(route('dev.login.store'), ['password' => config('services.debug_panel.password')])
        ->assertRedirect(route('dev.errors.index'));

    $this->get(route('dev.errors.index'))
        ->assertOk()
        ->assertSee('Error Tracker');
});

test('logout clears debug panel access', function () {
    $this->post(route('dev.login.store'), ['password' => config('services.debug_panel.password')]);
    $this->get(route('dev.errors.index'))->assertOk();

    $this->get(route('dev.logout'));

    $this->get(route('dev.errors.index'))->assertRedirect(route('dev.login'));
});

test('destroy, resolve and purge actions are guarded without session access', function () {
    $error = ErrorLog::create([
        'error_code' => 'ABC12345',
        'type' => 'exception',
        'severity' => 'error',
        'message' => 'Test error',
    ]);

    $this->post(route('dev.errors.resolve', $error->id))->assertRedirect(route('dev.login'));
    $this->delete(route('dev.errors.destroy', $error->id))->assertRedirect(route('dev.login'));
    $this->post(route('dev.errors.purge'))->assertRedirect(route('dev.login'));

    expect(ErrorLog::count())->toBe(1);
});

test('an authenticated debug session can resolve and delete an error log', function () {
    $this->post(route('dev.login.store'), ['password' => config('services.debug_panel.password')]);

    $error = ErrorLog::create([
        'error_code' => 'XYZ98765',
        'type' => 'javascript',
        'severity' => 'warning',
        'message' => 'Something broke',
    ]);

    $this->post(route('dev.errors.resolve', $error->id))
        ->assertJson(['resolved' => true]);

    expect($error->fresh()->resolved)->toBeTrue();

    $this->delete(route('dev.errors.destroy', $error->id));

    expect(ErrorLog::find($error->id))->toBeNull();
});

test('the error detail page shows the stack trace', function () {
    $this->post(route('dev.login.store'), ['password' => config('services.debug_panel.password')]);

    $error = ErrorLog::create([
        'error_code' => 'DEF45678',
        'type' => 'http',
        'status' => 500,
        'severity' => 'critical',
        'message' => 'Boom',
        'stack_trace' => 'Trace line 1',
    ]);

    $this->get(route('dev.errors.show', $error->error_code))
        ->assertOk()
        ->assertSee('DEF45678')
        ->assertSee('Trace line 1');
});

test('ErrorTracker redacts sensitive fields and never throws', function () {
    $log = ErrorTracker::capture([
        'message' => 'Leaked secret test',
        'context' => [
            'input' => ['email' => 'a@b.com', 'password' => 'supersecret', 'token' => 'abc'],
        ],
    ]);

    expect($log)->not->toBeNull()
        ->and($log->context['input']['password'])->toBe('[redacted]')
        ->and($log->context['input']['token'])->toBe('[redacted]')
        ->and($log->context['input']['email'])->toBe('a@b.com');
});

test('the public browser error intake endpoint accepts unauthenticated requests', function () {
    $this->postJson('/api/error-track', [
        'message' => 'TypeError: x is not a function',
        'stack' => 'at foo.js:10:5',
        'file' => 'foo.js',
        'line' => 10,
        'column' => 5,
        'url' => 'https://example.test/page',
    ])->assertOk()->assertJson(['ok' => true]);

    expect(ErrorLog::where('type', 'javascript')->count())->toBe(1);
});
