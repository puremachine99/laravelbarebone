<?php

use App\Livewire\Admin\RoleManagement;
use App\Models\Employee;
use App\Models\User;
use App\Support\ResourcePermissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function userWithPermissions(array $permissions): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), $permissions));

    return $user;
}

test('saving a role auto-adds view and the module permission for any ticked action', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->set('roleName', 'Employee Editor')
        ->set('rolePermissions', ['employees.update'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = Role::findByName('Employee Editor');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['access-internal-org', 'employees.update', 'employees.view']);
});

test('unknown resource permissions are dropped when saving a role', function () {
    $admin = userWithPermissions(['access-internal-org']);
    Permission::findOrCreate('access-technology');
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->set('roleName', 'Sneaky')
        ->set('rolePermissions', ['access-technology', 'employees.explode'])
        ->call('saveRole');

    expect(Role::findByName('Sneaky')->permissions->pluck('name')->all())->toBe(['access-technology']);
});

test('the role modal shows every module card with its own sub-menus and actions', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->assertSee('Application Modules')
        ->assertSee('Data Master')
        ->assertSee('Sentry')
        ->assertSee('Departments')
        ->assertSee('Positions')
        ->assertSee('Employees')
        ->assertSee('Export CSV')
        ->assertSee('page access only');
});

test('the other features section stays hidden while no standalone feature is registered', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->assertSeeInOrder(['Application Modules', 'Data Master'])
        ->assertDontSee('Standalone features that are not part of a module');
});

test('every catalog resource is rendered inside a card whose module permission is a real assignable permission', function () {
    $component = new RoleManagement;
    $assignable = $component->allAssignablePermissions();

    foreach (ResourcePermissions::groups() as $group) {
        expect($assignable)->toContain($group['module']);
    }

    expect($assignable)->toContain(...ResourcePermissions::names());
});

test('sub-menu pages are gated per page', function (string $module, string $resource, string $routeName) {
    $user = userWithPermissions([$module]);

    $this->actingAs($user)->get(route($routeName))->assertForbidden();

    $user->givePermissionTo("{$resource}.view");

    expect($this->actingAs($user->fresh())->get(route($routeName))->status())->not->toBe(403);
})->with([
    ['access-internal-org', 'departments', 'departments.index'],
    ['access-internal-org', 'positions', 'positions.index'],
    ['access-internal-org', 'employees', 'employees.index'],
]);

test('a role without the view permission cannot open a page even with module access', function () {
    $user = userWithPermissions(['access-internal-org']);

    $this->actingAs($user)->get(route('employees.index'))->assertForbidden();

    $user->givePermissionTo('employees.view');

    $this->actingAs($user->fresh())->get(route('employees.index'))->assertOk();
});

test('employees actions are gated per permission', function () {
    $target = User::factory()->create();
    $employee = Employee::create(['user_id' => $target->id]);

    $viewer = userWithPermissions(['access-internal-org', 'employees.view']);
    $this->actingAs($viewer);

    Volt::test('master.employees.index')->call('edit', $employee->id)->assertForbidden();
    Volt::test('master.employees.index')->call('confirmDelete', $target->id)->assertForbidden();
    Volt::test('master.employees.index')->call('toggleTrashed')->assertForbidden();
    Volt::test('master.employees.index')->call('restore', $target->id)->assertForbidden();

    $deleter = userWithPermissions(['access-internal-org', 'employees.view', 'employees.delete']);
    $this->actingAs($deleter);

    Volt::test('master.employees.index')->call('confirmDelete', $target->id)->call('delete');

    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();
});

test('employee csv export requires the export permission', function () {
    $viewer = userWithPermissions(['access-internal-org', 'employees.view']);
    $this->actingAs($viewer)->get(route('csv-export.download', 'employees'))->assertForbidden();

    $exporter = userWithPermissions(['access-internal-org', 'employees.view', 'employees.export']);
    $this->actingAs($exporter)->get(route('csv-export.download', 'employees'))->assertOk();
});

test('legacy grant gives roles full access, Direksi view only, and keeps departments write gated', function () {
    Permission::findOrCreate('access-internal-org');
    Permission::findOrCreate('modify-master-data');

    $orgAdmin = Role::findOrCreate('Internal Org Admin')->givePermissionTo('access-internal-org');
    $direksi = Role::findOrCreate('Direksi')->givePermissionTo('access-internal-org');
    $masterEditor = Role::findOrCreate('Master Editor')->givePermissionTo(['access-internal-org', 'modify-master-data']);
    $directUser = User::factory()->create();
    $directUser->givePermissionTo('access-internal-org');

    ResourcePermissions::grantLegacyAccess();

    expect($orgAdmin->fresh()->hasPermissionTo('employees.delete'))->toBeTrue()
        ->and($orgAdmin->fresh()->hasPermissionTo('departments.view'))->toBeTrue()
        ->and($orgAdmin->fresh()->hasPermissionTo('departments.update'))->toBeFalse()
        ->and($direksi->fresh()->hasPermissionTo('employees.view'))->toBeTrue()
        ->and($direksi->fresh()->hasPermissionTo('employees.update'))->toBeFalse()
        ->and($masterEditor->fresh()->hasPermissionTo('departments.update'))->toBeTrue()
        ->and($directUser->fresh()->hasPermissionTo('employees.restore'))->toBeTrue();
});

test('the seeder creates every catalog permission and Super Admin holds them all', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    foreach (ResourcePermissions::names() as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Role::findByName('Super Admin')->hasPermissionTo('employees.restore'))->toBeTrue();
});

test('the roles list shows user counts and a readable per-module breakdown instead of badges', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    $editor = Role::findOrCreate('Employee Editor')->givePermissionTo(['access-internal-org', 'employees.view', 'employees.update', 'departments.view']);
    User::factory()->count(2)->create()->each->assignRole($editor);

    Livewire::test(RoleManagement::class)
        ->assertSee('Employee Editor')
        ->assertSee('1 module')
        ->assertSee('2 / 3 sub-menus')
        ->assertSee('View · Update')
        ->assertSee('Edit');
});

test('a role with only view permissions is labelled read-only and Super Admin as full access', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Permission::findOrCreate('access-technology');

    Role::findOrCreate('Super Admin');
    Role::findOrCreate('Auditor')->givePermissionTo(['access-technology', 'tech-sentry.view']);

    $component = new RoleManagement;

    expect($component->roleSummary(Role::findByName('Auditor')->load('permissions'))['readOnly'])->toBeTrue();

    Livewire::test(RoleManagement::class)
        ->assertSee('Read-only')
        ->assertSee('Full access')
        ->assertSee('System role');
});
