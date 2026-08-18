<?php

use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.users'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-internal-org permission cannot access user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertStatus(403);
});

test('authorized users with access-internal-org permission can access user management', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('access-internal-org');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertStatus(200);
});

test('admin can assign roles and permissions dynamically to a user', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $targetUser = User::factory()->create();
    $role = Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    // Assert target user does not have the role initially
    expect($targetUser->hasRole('Partnership Admin'))->toBeFalse();

    // Open edit form and save with a new role assigned
    Livewire::test(UserManagement::class)
        ->call('openEdit', $targetUser->id)
        ->set('userRoles', ['Partnership Admin'])
        ->call('saveUser')
        ->assertHasNoErrors();

    // Assert changes were persisted
    $targetUser->refresh();
    expect($targetUser->hasRole('Partnership Admin'))->toBeTrue();
});

test('admin invites a new user with roles instead of setting a password directly', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('openCreate')
        ->set('name', 'New Employee')
        ->set('email', 'new.employee@example.com')
        ->set('userRoles', ['Partnership Admin'])
        ->call('saveUser')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'new.employee@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('New Employee')
        ->and($newUser->status)->toBe(UserStatus::Invited)
        ->and($newUser->invited_by)->toBe($admin->id)
        ->and($newUser->hasRole('Partnership Admin'))->toBeTrue();

    Notification::assertSentTo($newUser, UserInvitedNotification::class);
});

test('admin can update an existing user profile fields', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $targetUser = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('openEdit', $targetUser->id)
        ->set('name', 'Updated Name')
        ->set('email', $targetUser->email)
        ->call('saveUser')
        ->assertHasNoErrors();

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Updated Name');
});

test('admin can delete a user', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $targetUser = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('confirmDelete', $targetUser->id)
        ->call('delete');

    expect(User::find($targetUser->id))->toBeNull();
});

test('admin can resend an invite to a user who has not yet activated their account', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $invitedUser = User::factory()->invited()->create();

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('resendInvite', $invitedUser->id);

    Notification::assertSentTo($invitedUser, UserInvitedNotification::class);
});

test('admin can suspend an active user and their sessions are invalidated', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $targetUser = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'test-session-id',
        'user_id' => $targetUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('suspend', $targetUser->id);

    $targetUser->refresh();
    expect($targetUser->status)->toBe(UserStatus::Suspended);
    expect(DB::table('sessions')->where('user_id', $targetUser->id)->exists())->toBeFalse();
});

test('admin cannot suspend themselves', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('suspend', $admin->id);

    $admin->refresh();
    expect($admin->status)->toBe(UserStatus::Active);
});
