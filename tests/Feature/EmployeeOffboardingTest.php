<?php

use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeResigningEmployee(): Employee
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $employee = Employee::create(['user_id' => $user->id]);

    $user->givePermissionTo(moduleAccess('access-internal-org'));
    $user->assignRole(Role::findOrCreate('Internal Org Admin'));

    return $employee;
}

test('a non-active employee status suspends the user and cuts live sessions', function (EmployeeStatus $status) {
    $employee = makeResigningEmployee();
    DB::table('sessions')->insert([
        'id' => 'abc', 'user_id' => $employee->user_id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $employee->update(['status' => $status, 'status_date' => '2026-10-01']);

    expect($employee->user->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $employee->user_id)->exists())->toBeFalse();
})->with([EmployeeStatus::Resign, EmployeeStatus::Phk, EmployeeStatus::Discontinued, EmployeeStatus::Inactive]);

test('a resigned user is denied every ability even with permissions or Super Admin role', function () {
    $employee = makeResigningEmployee();
    $user = $employee->user;

    expect($user->can('access-internal-org'))->toBeTrue();

    $user->assignRole(Role::findOrCreate('Super Admin'));
    $employee->update(['status' => EmployeeStatus::Resign]);

    expect($user->fresh()->can('access-internal-org'))->toBeFalse();
});

test('a resigned user can no longer log in', function () {
    $employee = makeResigningEmployee();
    $employee->user->update(['password' => 'secret-password']);
    $employee->update(['status' => EmployeeStatus::Resign]);

    $this->post(route('login.store'), ['email' => $employee->user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

test('resigning keeps the user role and the employee record', function () {
    $employee = makeResigningEmployee();

    $employee->update(['status' => EmployeeStatus::Resign]);

    expect($employee->user->fresh()->hasRole('Internal Org Admin'))->toBeTrue()
        ->and($employee->fresh())->not->toBeNull();
});

test('restoring the employee to active restores access', function () {
    $employee = makeResigningEmployee();
    $employee->update(['status' => EmployeeStatus::Resign]);

    $employee->update(['status' => EmployeeStatus::Active]);

    expect($employee->user->fresh()->status)->toBe(UserStatus::Active)
        ->and($employee->user->fresh()->can('access-internal-org'))->toBeTrue();
});

test('the active scope excludes non-active employees', function () {
    $active = makeResigningEmployee();
    $resigned = makeResigningEmployee();
    $resigned->update(['status' => EmployeeStatus::Resign]);

    expect(Employee::active()->pluck('id')->all())->toBe([$active->id])
        ->and(Employee::count())->toBe(2);
});
