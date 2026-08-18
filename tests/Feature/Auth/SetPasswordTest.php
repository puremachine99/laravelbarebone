<?php

use App\Actions\Users\InviteUser;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Notification;

test('an invited user can set their password and activate their account', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $invited = app(InviteUser::class)->handle('New Hire', 'new.hire@example.com', null, [], $admin);

    $notification = null;
    Notification::assertSentTo($invited, UserInvitedNotification::class, function ($n) use (&$notification) {
        $notification = $n;

        return true;
    });

    $response = $this->post(route('password.setup.store', ['token' => $notification->token]), [
        'email' => $invited->email,
        'password' => 'a-strong-password',
        'password_confirmation' => 'a-strong-password',
    ]);

    $response->assertRedirect(route('login'));

    $invited->refresh();
    expect($invited->status)->toBe(UserStatus::Active)
        ->and($invited->email_verified_at)->not->toBeNull();

    $loginResponse = $this->post(route('login.store'), [
        'email' => $invited->email,
        'password' => 'a-strong-password',
    ]);

    $loginResponse->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('an invalid invite token is rejected', function () {
    $invited = User::factory()->invited()->create();

    $response = $this->post(route('password.setup.store', ['token' => 'not-a-real-token']), [
        'email' => $invited->email,
        'password' => 'a-strong-password',
        'password_confirmation' => 'a-strong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $invited->refresh();
    expect($invited->status)->toBe(UserStatus::Invited);
});
