<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
