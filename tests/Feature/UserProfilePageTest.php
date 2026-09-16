<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Grants the "investors" module to a department the same way the real app does — via
 * DepartmentModule rows — rather than assigning the "access-investors" permission
 * directly. EmployeeObserver::syncUserDepartment() re-syncs a user's module permissions
 * from their department on every Employee create/update, so a manually granted
 * permission gets silently revoked the moment the department doesn't list that module.
 * The permission row itself normally comes from RoleAndPermissionSeeder (not run here),
 * so it's created on demand.
 */
function grantInvestorsModule(Department $department): void
{
    Permission::findOrCreate('access-investors');
    $department->modules()->create(['module_code' => 'investors']);
}

test('guests are redirected to login', function () {
    $other = User::factory()->create();

    $this->get(route('users.show', $other))->assertRedirect(route('login'));
});

test('a user can view another user profile page', function () {
    $me = User::factory()->create();
    $department = Department::create(['name' => 'Finance']);
    $other = User::factory()->create(['name' => 'Jane Doe']);
    Employee::create(['user_id' => $other->id, 'department_id' => $department->id]);

    $this->actingAs($me);

    $response = $this->get(route('users.show', $other));

    $response->assertOk();
    $response->assertSee('Jane Doe');
    $response->assertSee('Finance');
});

test('visiting your own profile url redirects to the self profile page', function () {
    $me = User::factory()->create();
    $this->actingAs($me);

    $this->get(route('users.show', $me))->assertRedirect(route('profile.show'));
});

test('the KPI heatmap is visible on another user profile by default', function () {
    $me = User::factory()->create();
    $department = Department::create(['name' => 'Departemen Finance']);
    grantInvestorsModule($department);
    $other = User::factory()->create(['name' => 'Jane Doe']);
    $employee = Employee::create(['user_id' => $other->id, 'department_id' => $department->id]);
    FoTarget::factory()->create(['employee_id' => $employee->id, 'metric_key' => 'leads', 'yearly_target' => 300]);

    $this->actingAs($me);

    $this->get(route('users.show', $other))
        ->assertOk()
        ->assertSee('KPI Funding');
});

test('the KPI heatmap is hidden from other users once the employee opts out', function () {
    $me = User::factory()->create();
    $department = Department::create(['name' => 'Departemen Finance']);
    grantInvestorsModule($department);
    $other = User::factory()->create(['name' => 'Jane Doe']);
    $employee = Employee::create([
        'user_id' => $other->id,
        'department_id' => $department->id,
        'hide_kpi_heatmap' => true,
    ]);
    FoTarget::factory()->create(['employee_id' => $employee->id, 'metric_key' => 'leads', 'yearly_target' => 300]);

    $this->actingAs($me);

    $this->get(route('users.show', $other))
        ->assertOk()
        ->assertDontSee('KPI Funding');
});

test('the KPI heatmap is hidden from users without access-investors, even with a target set', function () {
    $me = User::factory()->create();
    $department = Department::create(['name' => 'Departemen Marketing']);
    $other = User::factory()->create(['name' => 'John Marketing']);
    $employee = Employee::create(['user_id' => $other->id, 'department_id' => $department->id]);
    FoTarget::factory()->create(['employee_id' => $employee->id, 'metric_key' => 'leads', 'yearly_target' => 300]);

    $this->actingAs($me);

    $this->get(route('users.show', $other))
        ->assertOk()
        ->assertDontSee('KPI Funding');
});
