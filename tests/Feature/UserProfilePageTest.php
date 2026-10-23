<?php

use App\Models\Department;
use App\Models\Employee;
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
