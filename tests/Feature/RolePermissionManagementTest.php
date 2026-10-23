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
    Permission::findOrCreate('access-technology');

    // 1. Create a new Role
    Livewire::test(RoleManagement::class)
        ->set('roleName', 'Technology Staff')
        ->set('rolePermissions', ['access-technology'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $newRole = Role::findByName('Technology Staff');
    expect($newRole)->not->toBeNull()
        ->and($newRole->hasPermissionTo('access-technology'))->toBeTrue()
        ->and($newRole->hasPermissionTo('access-internal-org'))->toBeFalse();

    // 2. Edit the Role permissions
    Livewire::test(RoleManagement::class)
        ->call('edit', $newRole->id)
        ->assertSet('roleName', 'Technology Staff')
        ->set('rolePermissions', ['access-technology', 'access-internal-org'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $newRole->refresh();
    expect($newRole->hasPermissionTo('access-internal-org'))->toBeTrue();

    // 3. Delete the Role
    Livewire::test(RoleManagement::class)
        ->call('confirmDelete', $newRole->id)
        ->assertSet('deleteTargetId', $newRole->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Role::where('name', 'Technology Staff')->exists())->toBeFalse();
});
