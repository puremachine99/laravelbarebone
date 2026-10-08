<?php

use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeResigningFo(): Employee
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $employee = Employee::create(['user_id' => $user->id]);

    $user->givePermissionTo(moduleAccess('access-investors'));
    $user->assignRole(Role::findOrCreate('Funding Officer'));

    return $employee;
}

test('a non-active employee status suspends the user and cuts live sessions', function (EmployeeStatus $status) {
    $employee = makeResigningFo();
    DB::table('sessions')->insert([
        'id' => 'abc', 'user_id' => $employee->user_id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $employee->update(['status' => $status, 'status_date' => '2026-10-01']);

    expect($employee->user->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $employee->user_id)->exists())->toBeFalse();
})->with([EmployeeStatus::Resign, EmployeeStatus::Phk, EmployeeStatus::Discontinued, EmployeeStatus::Inactive]);

test('a resigned user is denied every ability even with permissions or Super Admin role', function () {
    $employee = makeResigningFo();
    $user = $employee->user;

    expect($user->can('access-investors'))->toBeTrue();

    $user->assignRole(Role::findOrCreate('Super Admin'));
    $employee->update(['status' => EmployeeStatus::Resign]);

    expect($user->fresh()->can('access-investors'))->toBeFalse();
});

test('a resigned user can no longer log in', function () {
    $employee = makeResigningFo();
    $employee->user->update(['password' => 'secret-password']);
    $employee->update(['status' => EmployeeStatus::Resign]);

    $this->post(route('login.store'), ['email' => $employee->user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

test('resigning keeps the user role and the work they did', function () {
    $employee = makeResigningFo();
    $contact = FoContact::factory()->create(['employee_id' => $employee->id]);

    $employee->update(['status' => EmployeeStatus::Resign]);

    expect($employee->user->fresh()->hasRole('Funding Officer'))->toBeTrue()
        ->and($contact->fresh()->employee_id)->toBe($employee->id)
        ->and($contact->fresh()->employee->user->name)->toBe($employee->user->name);
});

test('restoring the employee to active restores access', function () {
    $employee = makeResigningFo();
    $employee->update(['status' => EmployeeStatus::Resign]);

    $employee->update(['status' => EmployeeStatus::Active]);

    expect($employee->user->fresh()->status)->toBe(UserStatus::Active)
        ->and($employee->user->fresh()->can('access-investors'))->toBeTrue();
});

test('the active scope excludes non-active employees', function () {
    $active = makeResigningFo();
    $resigned = makeResigningFo();
    $resigned->update(['status' => EmployeeStatus::Resign]);

    expect(Employee::active()->pluck('id')->all())->toBe([$active->id])
        ->and(Employee::count())->toBe(2);
});

function makeFundingTeam(): array
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);

    $make = function (string $name, string $role) use ($department): Employee {
        $user = User::factory()->create(['name' => $name, 'status' => UserStatus::Active]);
        $employee = Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
        $user->givePermissionTo(moduleAccess('access-investors'));
        $user->assignRole(Role::findOrCreate($role));

        return $employee;
    };

    return [$make('Admin FO', 'Investor Admin'), $make('Budi Aktif', 'Funding Officer'), $make('Oktavia Resign', 'Funding Officer')];
}

test('resigned funding officers are hidden by default and follow the switch set in fo management', function (string $component, string $property) {
    [$admin, , $oktavia] = makeFundingTeam();
    $oktavia->update(['status' => EmployeeStatus::Resign]);
    $this->actingAs($admin->user);

    $names = fn () => collect(Volt::test($component)->get($property))->pluck('name')->all();

    expect($names())->toContain('Budi Aktif')->not->toContain('Oktavia Resign (Resign)');

    Volt::test('investors.management-fo')->set('includeResigned', true);
    expect($names())->toContain('Budi Aktif', 'Oktavia Resign (Resign)');

    Volt::test('investors.management-fo')->set('includeResigned', false);
    expect($names())->not->toContain('Oktavia Resign (Resign)');
})->with([
    'daily dashboard' => ['investors.dashboard-index', 'foEmployees'],
    'monthly' => ['investors.monthly-index', 'foEmployees'],
    'conversion' => ['investors.conversion-index', 'foEmployees'],
    'fo management' => ['investors.management-fo', 'foEmployees'],
    'funnel' => ['investors.funnel-index', 'foOptions'],
]);

test('a regular funding officer cannot turn the switch on', function () {
    [, $budi, $oktavia] = makeFundingTeam();
    $oktavia->update(['status' => EmployeeStatus::Resign]);
    $this->actingAs($budi->user);

    Volt::test('investors.management-fo')->set('includeResigned', true)->assertSet('includeResigned', false);

    $names = collect(Volt::test('investors.dashboard-index')->get('foEmployees'))->pluck('name');

    expect($names)->not->toContain('Oktavia Resign (Resign)')
        ->and(session('show-resigned.investors'))->toBeNull();
});

test('the switch of one admin does not change what another admin sees', function () {
    [$admin, , $oktavia] = makeFundingTeam();
    $oktavia->update(['status' => EmployeeStatus::Resign]);

    $this->actingAs($admin->user);
    Volt::test('investors.management-fo')->set('includeResigned', true);

    session()->flush();

    expect(collect(Volt::test('investors.dashboard-index')->get('foEmployees'))->pluck('name'))
        ->not->toContain('Oktavia Resign (Resign)');
});

test('fo management lets an admin adjust the target of a resigned funding officer', function () {
    [$admin, , $oktavia] = makeFundingTeam();
    $oktavia->update(['status' => EmployeeStatus::Resign]);
    $admin->user->givePermissionTo('fo-targets.update');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->set('includeResigned', true)
        ->set('targetSettingEmployeeId', $oktavia->id)
        ->set('targetInputs.leads', '120')
        ->call('saveTargets')
        ->assertHasNoErrors();

    expect(FoTarget::where('employee_id', $oktavia->id)->where('metric_key', 'leads')->value('yearly_target'))->not->toBeNull();
});

test('the contact form only offers active funding officers plus the current pic', function () {
    [$admin, $budi, $oktavia] = makeFundingTeam();
    $oktavia->update(['status' => EmployeeStatus::Resign]);
    $this->actingAs($admin->user);

    $new = Volt::test('investors.contact-index')->get('picFormOptions')->pluck('name');
    expect($new)->toContain('Budi Aktif')->not->toContain('Oktavia Resign (Resign)');

    $editing = Volt::test('investors.contact-index')->set('employee_id', $oktavia->id)->get('picFormOptions')->pluck('name');
    expect($editing)->toContain('Budi Aktif', 'Oktavia Resign (Resign)');
});
