<?php

use App\Livewire\Admin\UserManagement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('users can be filtered by department', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    $this->actingAs($admin);

    $financeDept = Department::create(['name' => 'Departemen Finance']);
    $techDept = Department::create(['name' => 'Departemen Technology']);

    $financeUser = User::factory()->create(['name' => 'Finance Person']);
    Employee::create(['user_id' => $financeUser->id, 'department_id' => $financeDept->id]);

    $techUser = User::factory()->create(['name' => 'Tech Person']);
    Employee::create(['user_id' => $techUser->id, 'department_id' => $techDept->id]);

    Livewire::test(UserManagement::class)
        ->set('departmentId', $financeDept->id)
        ->assertSee('Finance Person')
        ->assertDontSee('Tech Person');

    Livewire::test(UserManagement::class)
        ->set('departmentId', '')
        ->assertSee('Finance Person')
        ->assertSee('Tech Person');
});
