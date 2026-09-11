<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('users without an employee profile still appear in the employees list and are searchable', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    $this->actingAs($admin);

    $orphanUser = User::factory()->create([
        'name' => 'Orphan Account',
        'email' => 'orphan.account@example.com',
    ]);

    $completeUser = User::factory()->create(['name' => 'Complete Person']);
    Employee::create(['user_id' => $completeUser->id]);

    Volt::test('master.employees.index')
        ->assertSee('Orphan Account')
        ->assertSee('Complete Person')
        ->set('search', 'orphan.account@example.com')
        ->assertSee('Orphan Account')
        ->assertDontSee('Complete Person');
});

test('admin can complete the employee profile for a user who has none yet', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));
    $this->actingAs($admin);

    $orphanUser = User::factory()->create(['name' => 'Orphan Account']);
    $department = Department::create(['name' => 'Departemen Finance']);

    Volt::test('master.employees.index')
        ->call('completeProfile', $orphanUser->id)
        ->set('department_id', $department->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Employee::where('user_id', $orphanUser->id)->first())
        ->not->toBeNull()
        ->department_id->toBe($department->id);
});
