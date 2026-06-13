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

test('unauthorized users without manage-internal-org permission cannot access user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertStatus(403);
});

test('authorized users with manage-internal-org permission can access user management', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('manage-internal-org');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertStatus(200);
});

test('admin can assign roles and permissions dynamically to a user', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage-internal-org'));

    $targetUser = User::factory()->create();
    $role = Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    // Assert target user does not have the role initially
    expect($targetUser->hasRole('Partnership Admin'))->toBeFalse();

    // Call Livewire component to save permissions
    Livewire::test(UserManagement::class)
        ->set('selectedUser', $targetUser)
        ->set('userRoles', ['Partnership Admin'])
        ->call('savePermissions')
        ->assertHasNoErrors();

    // Assert changes were persisted
    $targetUser->refresh();
    expect($targetUser->hasRole('Partnership Admin'))->toBeTrue();
});
