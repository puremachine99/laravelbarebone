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
