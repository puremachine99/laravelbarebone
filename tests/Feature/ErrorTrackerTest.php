<?php

use App\Enums\UserStatus;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function loginAsSuperAdminForDevPanel(): void
{
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    test()->actingAs($superAdmin);
}

test('guests get a 404, not a redirect to login', function () {
    $this->get(route('dev.errors.index'))
        ->assertNotFound();
});

test('non super admins get a 404, not a 403', function () {
    $regularUser = User::factory()->create(['status' => UserStatus::Active]);

    $this->actingAs($regularUser)
        ->get(route('dev.errors.index'))
        ->assertNotFound();
});

test('super admins open the error tracker directly, without the debug panel password', function () {
    loginAsSuperAdminForDevPanel();

    $this->get(route('dev.errors.index'))
        ->assertOk()
        ->assertSee('Sentry');
});

test('the error tracker is served under /tech/sentry and the old url redirects there', function () {
    expect(route('dev.errors.index', absolute: false))->toBe('/tech/sentry');

    $this->get('/dev/errors')->assertNotFound();

    loginAsSuperAdminForDevPanel();
    $this->get('/dev/errors')->assertRedirect('/tech/sentry');
});

test('the technology menu links super admins to the error tracker', function () {
    loginAsSuperAdminForDevPanel();

    $this->get(route('documentation'))->assertSee(route('dev.errors.index'));
});

test('destroy, resolve and purge actions are hidden from non super admins', function () {
    $this->actingAs(User::factory()->create(['status' => UserStatus::Active]));

    $error = ErrorLog::create([
        'error_code' => 'ABC12345',
        'type' => 'exception',
        'severity' => 'error',
        'message' => 'Test error',
    ]);

    $this->post(route('dev.errors.resolve', $error->id))->assertNotFound();
    $this->delete(route('dev.errors.destroy', $error->id))->assertNotFound();
    $this->post(route('dev.errors.purge'))->assertNotFound();

    expect(ErrorLog::count())->toBe(1);
});

test('a super admin can resolve and delete an error log', function () {
    loginAsSuperAdminForDevPanel();

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
    loginAsSuperAdminForDevPanel();

    $error = ErrorLog::create([
        'error_code' => 'DEF45678',
        'type' => 'http',
        'status' => 500,
        'severity' => 'critical',
        'message' => 'Boom',
        'stack_trace' => 'Trace line 1',
        'user_name' => 'Siti',
        'context' => ['ip' => '172.71.215.58', 'user_agent' => 'Mozilla/5.0 TestAgent'],
    ]);

    $this->get(route('dev.errors.show', $error->error_code))
        ->assertOk()
        ->assertSee('DEF45678')
        ->assertSee('Trace line 1')
        ->assertSee('Kode untuk dilaporkan user')
        ->assertSee('Copy for AI')
        ->assertSee('Siti')
        ->assertSee('172.71.215.58')
        ->assertSee('Mozilla/5.0 TestAgent');
});

test('deleting from the detail page returns to the list', function () {
    loginAsSuperAdminForDevPanel();

    $error = ErrorLog::create(['error_code' => 'GHI11111', 'type' => 'http', 'severity' => 'error', 'message' => 'Boom']);

    $this->delete(route('dev.errors.destroy', $error->id), ['to_index' => 1])
        ->assertRedirect(route('dev.errors.index'));

    expect(ErrorLog::count())->toBe(0);
});

test('captured errors record the client ip and user agent', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_USER_AGENT' => 'TestBrowser/1.0'])
        ->postJson('/api/error-track', ['message' => 'TypeError'])
        ->assertOk();

    expect(ErrorLog::first()->context)
        ->toMatchArray(['ip' => '10.1.2.3', 'user_agent' => 'TestBrowser/1.0']);
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
