<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('employees can be filtered by department', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    $this->actingAs($admin);

    $financeDept = Department::create(['name' => 'Departemen Finance']);
    $techDept = Department::create(['name' => 'Departemen Technology']);

    $financeUser = User::factory()->create(['name' => 'Finance Person']);
    Employee::create(['user_id' => $financeUser->id, 'department_id' => $financeDept->id]);

    $techUser = User::factory()->create(['name' => 'Tech Person']);
    Employee::create(['user_id' => $techUser->id, 'department_id' => $techDept->id]);

    Volt::test('master.employees.index')
        ->set('filterDept', $financeDept->id)
        ->assertSee('Finance Person')
        ->assertDontSee('Tech Person');

    Volt::test('master.employees.index')
        ->set('filterDept', '')
        ->assertSee('Finance Person')
        ->assertSee('Tech Person');
});
