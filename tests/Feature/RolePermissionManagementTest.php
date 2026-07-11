<?php

use App\Livewire\Admin\RoleManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.roles'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-internal-org permission cannot access role management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.roles'))
        ->assertStatus(403);
});

test('authorized users with access-internal-org permission can access role management', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('access-internal-org');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('admin.roles'))
        ->assertStatus(200)
        ->assertSeeLivewire(RoleManagement::class);
});

test('role management component CRUD operations', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $this->actingAs($admin);

    // Create permissions to assign
    $perm1 = Permission::findOrCreate('access-partnership');
    $perm2 = Permission::findOrCreate('access-products');

    // 1. Create a new Role
    Livewire::test(RoleManagement::class)
        ->set('roleName', 'Partnership Staff')
        ->set('rolePermissions', ['access-partnership'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $newRole = Role::findByName('Partnership Staff');
    expect($newRole)->not->toBeNull()
        ->and($newRole->hasPermissionTo('access-partnership'))->toBeTrue()
        ->and($newRole->hasPermissionTo('access-products'))->toBeFalse();

    // 2. Edit the Role permissions
    Livewire::test(RoleManagement::class)
        ->call('edit', $newRole->id)
        ->assertSet('roleName', 'Partnership Staff')
        ->set('rolePermissions', ['access-partnership', 'access-products'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $newRole->refresh();
    expect($newRole->hasPermissionTo('access-products'))->toBeTrue();

    // 3. Delete the Role
    Livewire::test(RoleManagement::class)
        ->call('confirmDelete', $newRole->id)
        ->assertSet('deleteTargetId', $newRole->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Role::where('name', 'Partnership Staff')->exists())->toBeFalse();
});
