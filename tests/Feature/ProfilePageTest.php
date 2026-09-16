<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('profile page is rendered for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('profile.show'));

    $response->assertOk();
});

test('guests are redirected to login', function () {
    $response = $this->get(route('profile.show'));

    $response->assertRedirect(route('login'));
});

test('the delete account section is not shown on the main profile page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('Delete account');
});

test('can update name and email from the profile page', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com');
});

test('can set an optional secondary company email and username', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('secondary_email', 'someone@smartid.co.id')
        ->set('username', 'someone123')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh())
        ->secondary_email->toBe('someone@smartid.co.id')
        ->username->toBe('someone123');
});

test('secondary email and username are optional and can be left blank', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('secondary_email', '')
        ->set('username', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh())
        ->secondary_email->toBeNull()
        ->username->toBeNull();
});

test('secondary email must be a smartid.co.id company address', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('secondary_email', 'someone@gmail.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['secondary_email']);
});

test('username must be alphanumeric with no symbols', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('username', 'some_one-123!')
        ->call('updateProfileInformation')
        ->assertHasErrors(['username']);
});

test('username must be unique across users', function () {
    User::factory()->create(['username' => 'takenname']);
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('username', 'takenname')
        ->call('updateProfileInformation')
        ->assertHasErrors(['username']);
});

test('secondary email must be unique across users', function () {
    User::factory()->create(['secondary_email' => 'dupe@smartid.co.id']);
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('secondary_email', 'dupe@smartid.co.id')
        ->call('updateProfileInformation')
        ->assertHasErrors(['secondary_email']);
});

test('can upload a profile photo', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.index')
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('uploadAvatar')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($user->avatar_path);
});

test('can remove a profile photo', function () {
    Storage::fake('s3');

    $user = User::factory()->create(['avatar_path' => 'avatars/existing.jpg']);
    Storage::disk('s3')->put('avatars/existing.jpg', 'fake-content');
    $this->actingAs($user);

    Volt::test('profile.index')
        ->call('removeAvatar');

    $user->refresh();

    expect($user->avatar_path)->toBeNull();
    Storage::disk('s3')->assertMissing('avatars/existing.jpg');
});

test('user can delete their account from the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('profile.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('profile.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
