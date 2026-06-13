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
    $this->get(route('opd.index'))->assertOk();
    $this->get(route('products.index'))->assertOk();
    $this->get(route('executive-dashboard'))->assertOk();
    $this->get(route('leaderboard'))->assertOk();
    $this->get(route('partnership.pitching'))->assertOk();
    $this->get(route('partnership.realisasi'))->assertOk();
    $this->get(route('finance.intake'))->assertOk();
    $this->get(route('finance.reconciliation'))->assertOk();
    $this->get(route('finance.disbursements'))->assertOk();
    $this->get(route('finance.clearance-archive'))->assertOk();
    $this->get(route('chat'))->assertOk();
    $this->get(route('ai-center'))->assertOk();
});

test('user without proper role cannot access internal organization or new modules', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('departments.index'))->assertStatus(403);
    $this->get(route('executive-dashboard'))->assertStatus(403);
    $this->get(route('leaderboard'))->assertStatus(403);
    $this->get(route('partnership.pitching'))->assertStatus(403);
    $this->get(route('partnership.realisasi'))->assertStatus(403);
    $this->get(route('finance.intake'))->assertStatus(403);
    $this->get(route('chat'))->assertStatus(403);
});

test('internal org admin can access internal org but not partnership or finance', function () {
    $user = User::factory()->create();
    $user->assignRole('Internal Org Admin');
    $this->actingAs($user);

    $this->get(route('departments.index'))->assertOk();
    $this->get(route('opd.index'))->assertStatus(403);
    $this->get(route('finance.intake'))->assertStatus(403);
});

test('partnership admin can access partnership but not internal org', function () {
    $user = User::factory()->create();
    $user->assignRole('Partnership Admin');
    $this->actingAs($user);

    $this->get(route('opd.index'))->assertOk();
    $this->get(route('departments.index'))->assertStatus(403);
});
