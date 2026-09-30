<?php

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('new employees default to active status', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('resetForm')
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi.santoso@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $employee = Employee::whereHas('user', fn ($q) => $q->where('email', 'budi.santoso@example.com'))->first();

    expect($employee->status)->toBe(EmployeeStatus::Active)
        ->and($employee->status_date)->toBeNull()
        ->and($employee->status_reason)->toBeNull();
});

test('marking an employee as resigned stores the status date and reason', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $employeeUser = User::factory()->create();
    $employee = Employee::create(['user_id' => $employeeUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('status', EmployeeStatus::Resign->value)
        ->set('status_date', '2026-09-01')
        ->set('status_reason', 'Resign untuk melanjutkan studi.')
        ->call('save')
        ->assertHasNoErrors();

    $employee->refresh();

    expect($employee->status)->toBe(EmployeeStatus::Resign)
        ->and($employee->status_date->format('Y-m-d'))->toBe('2026-09-01')
        ->and($employee->status_reason)->toBe('Resign untuk melanjutkan studi.');
});

test('setting status back to active clears the status date and reason', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $employeeUser = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $employeeUser->id,
        'status' => EmployeeStatus::Phk,
        'status_date' => '2026-08-01',
        'status_reason' => 'PHK karena efisiensi.',
    ]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('status', EmployeeStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    $employee->refresh();

    expect($employee->status)->toBe(EmployeeStatus::Active)
        ->and($employee->status_date)->toBeNull()
        ->and($employee->status_reason)->toBeNull();
});

test('status must be a valid employee status value', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $employeeUser = User::factory()->create();
    $employee = Employee::create(['user_id' => $employeeUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('status', 'not-a-real-status')
        ->call('save')
        ->assertHasErrors(['status']);
});
