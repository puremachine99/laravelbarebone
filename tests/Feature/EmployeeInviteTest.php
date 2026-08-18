<?php

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('adding a new employee sends an invitation instead of setting a password directly', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('resetForm')
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi.santoso@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'budi.santoso@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->status)->toBe(UserStatus::Invited)
        ->and($newUser->invited_by)->toBe($admin->id);

    expect(Employee::where('user_id', $newUser->id)->exists())->toBeTrue();

    Notification::assertSentTo($newUser, UserInvitedNotification::class);
});

test('editing an existing employee does not require a password and can update one optionally', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('access-internal-org'));

    $employeeUser = User::factory()->create(['name' => 'Old Name']);
    $employee = Employee::create(['user_id' => $employeeUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($employee->user->fresh()->name)->toBe('New Name');
});
