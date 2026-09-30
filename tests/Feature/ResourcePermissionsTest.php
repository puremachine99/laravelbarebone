<?php

use App\Livewire\Admin\RoleManagement;
use App\Models\Employee;
use App\Models\FoContact;
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
        ->set('roleName', 'Contact Editor')
        ->set('rolePermissions', ['fo-contacts.update'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = Role::findByName('Contact Editor');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['access-investors', 'fo-contacts.update', 'fo-contacts.view']);
});

test('unknown resource permissions are dropped when saving a role', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->set('roleName', 'Sneaky')
        ->set('rolePermissions', ['access-partnership', 'fo-contacts.explode'])
        ->call('saveRole');

    expect(Role::findByName('Sneaky')->permissions->pluck('name')->all())->toBe(['access-partnership']);
});

test('the role modal shows every module card with its own sub-menus and actions', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->assertSee('Application Modules')
        ->assertSee('Data Master')
        ->assertSee('Other Features')
        ->assertSee('Clearance Archive')
        ->assertSee('Timeline Calculator')
        ->assertSee('Master Document')
        ->assertSee('Contact Positions')
        ->assertSee('Export CSV')
        ->assertSee('page access only');
});

test('the other features section is rendered after every module and data master section', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    Livewire::test(RoleManagement::class)
        ->assertSeeInOrder(['Application Modules', 'Data Master', 'Other Features', 'Executive Dashboard']);
});

test('every catalog resource is rendered inside a card whose module permission is a real assignable permission', function () {
    $component = new RoleManagement;
    $assignable = $component->allAssignablePermissions();

    foreach (ResourcePermissions::groups() as $group) {
        expect($assignable)->toContain($group['module']);
    }

    expect($assignable)->toContain(...ResourcePermissions::names());
});

test('sub-menu pages that only have view access are gated per page', function (string $module, string $resource, string $routeName) {
    $user = userWithPermissions([$module]);

    $this->actingAs($user)->get(route($routeName))->assertForbidden();

    $user->givePermissionTo("{$resource}.view");

    expect($this->actingAs($user->fresh())->get(route($routeName))->status())->not->toBe(403);
})->with([
    ['access-finance', 'finance-intake', 'finance.intake'],
    ['access-finance', 'finance-clearance-archive', 'finance.clearance-archive'],
    ['access-partnership', 'partnership-pitching', 'partnership.pitching'],
    ['access-partnership', 'region', 'region.index'],
    ['access-investors', 'investors-monthly', 'investors.monthly'],
    ['access-products', 'products', 'products.index'],
    ['access-technology', 'tech-timeline-calc', 'tech.timeline-calc'],
]);

test('a role without the view permission cannot open a page even with module access', function () {
    $user = userWithPermissions(['access-investors']);

    $this->actingAs($user)->get(route('investors.contacts'))->assertForbidden();

    $user->givePermissionTo('fo-contacts.view');

    $this->actingAs($user->fresh())->get(route('investors.contacts'))->assertOk();
});

test('view-only users cannot create update or delete fo contacts', function () {
    $user = userWithPermissions(['access-investors', 'fo-contacts.view']);
    $contact = FoContact::factory()->create();
    $this->actingAs($user);

    Volt::test('investors.contact-index')
        ->call('showCreate')->assertForbidden();

    Volt::test('investors.contact-index')
        ->call('editContact', $contact->id)->assertForbidden();

    Volt::test('investors.contact-index')
        ->call('deleteContact', $contact->id)->assertForbidden();

    expect($contact->fresh())->not->toBeNull();
});

test('create permission alone allows adding a contact but not deleting it', function () {
    $user = userWithPermissions(['access-investors', 'fo-contacts.view', 'fo-contacts.create']);
    $this->actingAs($user);

    Volt::test('investors.contact-index')
        ->call('showCreate')
        ->set('nama', 'Kontak Baru')
        ->call('saveContact')
        ->assertHasNoErrors();

    $contact = FoContact::where('nama', 'Kontak Baru')->firstOrFail();

    Volt::test('investors.contact-index')
        ->call('deleteContact', $contact->id)->assertForbidden();
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

test('legacy migration keeps existing access: full for roles, view only for Direksi, departments write stays gated', function () {
    Permission::findOrCreate('access-investors');
    Permission::findOrCreate('access-internal-org');
    Permission::findOrCreate('modify-master-data');

    $investorAdmin = Role::findOrCreate('Investor Admin')->givePermissionTo('access-investors');
    $direksi = Role::findOrCreate('Direksi')->givePermissionTo('access-investors');
    $orgAdmin = Role::findOrCreate('Internal Org Admin')->givePermissionTo('access-internal-org');
    $masterEditor = Role::findOrCreate('Master Editor')->givePermissionTo(['access-internal-org', 'modify-master-data']);
    $directUser = User::factory()->create();
    $directUser->givePermissionTo('access-investors');

    ResourcePermissions::grantLegacyAccess();

    expect($investorAdmin->fresh()->hasPermissionTo('fo-funnel.delete'))->toBeTrue()
        ->and($direksi->fresh()->hasPermissionTo('fo-funnel.view'))->toBeTrue()
        ->and($direksi->fresh()->hasPermissionTo('fo-funnel.update'))->toBeFalse()
        ->and($orgAdmin->fresh()->hasPermissionTo('employees.delete'))->toBeTrue()
        ->and($orgAdmin->fresh()->hasPermissionTo('departments.view'))->toBeTrue()
        ->and($orgAdmin->fresh()->hasPermissionTo('departments.update'))->toBeFalse()
        ->and($masterEditor->fresh()->hasPermissionTo('departments.update'))->toBeTrue()
        ->and($directUser->fresh()->hasPermissionTo('investor-documents.restore'))->toBeTrue();
});

test('the seeder creates every catalog permission and Super Admin holds them all', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    foreach (ResourcePermissions::names() as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Role::findByName('Super Admin'
    )->hasPermissionTo('employees.restore'))->toBeTrue();
});

test('the roles list shows user counts and a readable per-module breakdown instead of badges', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    ResourcePermissions::ensureExist();
    Permission::findOrCreate('access-investors');

    $editor = Role::findOrCreate('Contact Editor')->givePermissionTo(['access-investors', 'fo-contacts.view', 'fo-contacts.update', 'investors-monthly.view']);
    User::factory()->count(2)->create()->each->assignRole($editor);

    Livewire::test(RoleManagement::class)
        ->assertSee('Contact Editor')
        ->assertSee('1 module')
        ->assertSee('2 / 8 sub-menus')
        ->assertSee('View · Update')
        ->assertSee('No access')
        ->assertSee('Edit');
});

test('a role with only view permissions is labelled read-only and Super Admin as full access', function () {
    $admin = userWithPermissions(['access-internal-org']);
    $this->actingAs($admin);

    ResourcePermissions::ensureExist();
    Permission::findOrCreate('access-finance');

    Role::findOrCreate('Super Admin');
    Role::findOrCreate('Auditor')->givePermissionTo(['access-finance', 'finance-intake.view']);

    $component = new RoleManagement;

    expect($component->roleSummary(Role::findByName('Auditor')->load('permissions'))['readOnly'])->toBeTrue();

    Livewire::test(RoleManagement::class)
        ->assertSee('Read-only')
        ->assertSee('Full access')
        ->assertSee('System role');
});
