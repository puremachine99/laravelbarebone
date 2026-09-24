<?php

use App\Enums\UserStatus;
use App\Http\Controllers\Debug\ErrorLogController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('super admin can force login as an active user', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $target = User::factory()->create(['status' => UserStatus::Active]);

    $this->actingAs($superAdmin);

    Volt::test('dev.force-login')
        ->call('confirm', $target->id)
        ->call('loginAs');

    expect(auth()->id())->toBe($target->id);
});

test('non super admin gets a 404, not a 403', function () {
    $regularUser = User::factory()->create(['status' => UserStatus::Active]);

    $this->actingAs($regularUser)
        ->withSession([ErrorLogController::SESSION_KEY => true])
        ->get(route('dev.force-login'))
        ->assertNotFound();
});

test('guests get a 404, not a redirect to login', function () {
    $this->get(route('dev.force-login'))->assertNotFound();
});

test('super admin without an unlocked debug panel session gets a 404', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $this->actingAs($superAdmin)
        ->get(route('dev.force-login'))
        ->assertNotFound();
});

test('super admin with an unlocked debug panel session can reach the force login page', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $this->actingAs($superAdmin)
        ->withSession([ErrorLogController::SESSION_KEY => true])
        ->get(route('dev.force-login'))
        ->assertOk();
});

test('super admin cannot force login as a suspended or invited user', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $suspended = User::factory()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($superAdmin);

    Volt::test('dev.force-login')
        ->call('confirm', $suspended->id)
        ->call('loginAs');

    expect(auth()->id())->toBe($superAdmin->id);
});
