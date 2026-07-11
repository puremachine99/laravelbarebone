<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('guests are redirected to login from partnership employees', function () {
    $this->get(route('partnership-employees.index'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-partnership permission cannot access partnership employees page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('partnership-employees.index'))
        ->assertStatus(403);
});

test('authorized users with access-partnership permission can access partnership employees page', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('access-partnership');
    $user->givePermissionTo($permission);

    // Ensure target department exists
    Department::create(['name' => 'Departemen Revenue and Growth']);

    $this->actingAs($user)
        ->get(route('partnership-employees.index'))
        ->assertStatus(200)
        ->assertSeeLivewire('partnership.pmo-index');
});

test('partnership employees CRUD functionality with region assignment', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('access-partnership');
    $user->givePermissionTo($permission);
    $user->givePermissionTo(Permission::findOrCreate('modify-master-data'));

    $this->actingAs($user);

    $dept = Department::create(['name' => 'Departemen Revenue and Growth']);
    $region = Region::create(['name' => 'Jawa Timur', 'code' => 'REG-JAWA']);

    // Initial state check
    $component = Livewire::test('partnership.pmo-index')
        ->assertSet('employee_id', null)
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('region_id', '');

    // 1. Save Partnership Employee with Region
    $component->set('name', 'PMO Budi')
        ->set('email', 'budi.pmo@smartid.co.id')
        ->set('password', 'password123')
        ->set('region_id', $region->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    $this->assertDatabaseHas('users', [
        'name' => 'PMO Budi',
        'email' => 'budi.pmo@smartid.co.id',
    ]);

    $pmo = Employee::whereHas('user', fn ($q) => $q->where('name', 'PMO Budi'))->first();
    $this->assertEquals($dept->id, $pmo->department_id);
    $this->assertEquals($region->id, $pmo->region_id);

    // 2. Edit Employee Region
    $region2 = Region::create(['name' => 'Jawa Barat', 'code' => 'REG-JABAR']);

    $component->call('edit', $pmo->id)
        ->assertSet('employee_id', $pmo->id)
        ->assertSet('name', 'PMO Budi')
        ->assertSet('region_id', $region->id)
        ->set('region_id', $region2->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertEquals($region2->id, $pmo->fresh()->region_id);

    // 3. Delete Employee
    $component->call('confirmDelete', $pmo->id)
        ->assertSet('deleteTargetId', $pmo->id)
        ->call('delete')
        ->assertSet('deleteTargetId', null);

    $this->assertSoftDeleted('employees', [
        'id' => $pmo->id,
    ]);
});
