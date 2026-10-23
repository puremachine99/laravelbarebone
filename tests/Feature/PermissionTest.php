<?php

use App\Models\User;

beforeEach(function () {
    // Seed roles and permissions for testing
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

test('super admin can access everything', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');
    $this->actingAs($superAdmin);

    $this->get(route('departments.index'))->assertOk();
    $this->get(route('positions.index'))->assertOk();
    $this->get(route('employees.index'))->assertOk();
    $this->get(route('admin.roles'))->assertOk();
    $this->get(route('dev.errors.index'))->assertOk();
});

test('user without proper role cannot access internal organization', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('departments.index'))->assertStatus(403);
    $this->get(route('employees.index'))->assertStatus(403);
    $this->get(route('admin.roles'))->assertStatus(403);
    $this->get(route('dev.errors.index'))->assertNotFound();
});

test('internal org admin can access internal org but not sentry', function () {
    $user = User::factory()->create();
    $user->assignRole('Internal Org Admin');
    $this->actingAs($user);

    $this->get(route('departments.index'))->assertOk();
    $this->get(route('employees.index'))->assertOk();
    $this->get(route('dev.errors.index'))->assertNotFound();
});

test('technology role cannot access internal org', function () {
    $user = User::factory()->create();
    $user->assignRole('Technology');
    $this->actingAs($user);

    $this->get(route('departments.index'))->assertStatus(403);
});
