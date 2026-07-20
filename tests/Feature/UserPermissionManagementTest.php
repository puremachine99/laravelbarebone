<?php

use App\Livewire\Admin\UserManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('admin can create a new user with roles', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('openCreate')
        ->set('name', 'New Employee')
        ->set('email', 'new.employee@example.com')
        ->set('password', 'password123')
        ->set('userRoles', ['Partnership Admin'])
        ->call('saveUser')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'new.employee@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('New Employee')
        ->and($newUser->hasRole('Partnership Admin'))->toBeTrue();
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
