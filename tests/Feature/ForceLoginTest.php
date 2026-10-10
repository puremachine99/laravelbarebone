<?php

use App\Enums\UserStatus;
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

test('force login is a tab on the Sentry page for super admins', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $this->actingAs($superAdmin)
        ->get(route('dev.errors.index', ['tab' => 'force-login']))
        ->assertOk()
        ->assertSeeLivewire('dev.force-login');
});

test('non super admins and guests get a 404, not a 403 or a redirect', function () {
    $this->get(route('dev.errors.index', ['tab' => 'force-login']))->assertNotFound();

    $this->actingAs(User::factory()->create(['status' => UserStatus::Active]))
        ->get(route('dev.errors.index', ['tab' => 'force-login']))
        ->assertNotFound();
});

test('the component itself refuses anyone but a super admin', function () {
    $this->actingAs(User::factory()->create(['status' => UserStatus::Active]));

    Volt::test('dev.force-login')->assertNotFound();
});

test('the old force login url redirects to the Sentry tab', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $this->actingAs($superAdmin)
        ->get('/dev/force-login')
        ->assertRedirect('/tech/sentry?tab=force-login');
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
