<?php

use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users can authenticate using their secondary company email', function () {
    $user = User::factory()->create(['secondary_email' => 'secondary@smartid.co.id']);

    $response = $this->post(route('login.store'), [
        'email' => 'secondary@smartid.co.id',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can authenticate using their username', function () {
    $user = User::factory()->create(['username' => 'someone123']);

    $response = $this->post(route('login.store'), [
        'email' => 'someone123',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('an unrecognized identifier fails login with a generic message', function () {
    $response = $this->post(route('login.store'), [
        'email' => 'nobody-matches-anything',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
});

test('session cookie does not persist after the browser closes when remember me is unchecked', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionCookie = $response->headers->getCookies()[array_search(
        config('session.cookie'),
        array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies())
    )];

    expect($sessionCookie->getExpiresTime())->toBe(0);
    expect($response->headers->getCookies())
        ->not->toContain(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));
});

test('remember me keeps the user authenticated beyond the session cookie lifetime', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $rememberCookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($rememberCookie)->not->toBeNull();
    expect($rememberCookie->getExpiresTime())->toBeGreaterThan(now()->addYear()->timestamp);
});

test('failed login shows a generic message regardless of whether the email is registered', function () {
    $user = User::factory()->create();

    $unknownEmailResponse = $this->post(route('login.store'), [
        'email' => 'not-registered@example.com',
        'password' => 'password',
    ]);

    $wrongPasswordResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $unknownEmailResponse->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $wrongPasswordResponse->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('there is no public registration route', function () {
    $this->get('/register')->assertNotFound();
});

test('an invited user who has not set a password cannot log in', function () {
    $user = User::factory()->invited()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a suspended user cannot log in', function () {
    $user = User::factory()->suspended()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a suspended user is logged out of an existing session on their next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['status' => UserStatus::Suspended]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the login screen shows the animated photo collage beside a compact form', function () {
    expect(file_exists(public_path('images/auth/login-collage.webp')))->toBeTrue();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('images/auth/login-collage.webp', false)
        ->assertSee('auth-hero-image', false)
        ->assertSee('lg:grid-cols-2', false)
        ->assertSee('auth-paper', false)
        ->assertSeeInOrder(['class="h-[1em] w-auto object-contain drop-shadow-lg">', 'SmartID</span>', 'ERP</span>'], false)
        ->assertDontSee('Kelola operasional, funding, dan partnership');
});

test('auth pages always use the default light theme, even for a user on a dark custom theme', function () {
    $user = User::factory()->create(['theme' => 'espresso', 'appearance' => 'dark']);

    $this->get(route('login'))->assertOk()->assertSee('window.themeLocked = true;', false);

    // confirm-password uses the auth layout while signed in
    $this->actingAs($user)->get(route('password.confirm'))->assertOk()->assertSee('window.themeLocked = true;', false);

    // regular pages keep following the account
    $this->actingAs($user)->get(route('profile.show'))->assertOk()->assertSee('window.themeLocked = false;', false);
});

test('every page uses the SmartID icon as favicon', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.asset('smartid.png').'" type="image/png">', false)
        ->assertDontSee('favicon.svg');
});
