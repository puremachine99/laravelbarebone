<?php

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.users'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-internal-org permission cannot access user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertStatus(403);
});

test('the legacy admin.users URL redirects to the merged employees page', function () {
    $user = User::factory()->create();
    $permission = moduleAccess('access-internal-org');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertRedirect(route('employees.index'));
});

test('admin can assign roles and permissions dynamically to a user', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $targetUser = User::factory()->create();
    $employee = Employee::create(['user_id' => $targetUser->id]);
    Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    expect($targetUser->hasRole('Partnership Admin'))->toBeFalse();

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('userRoles', ['Partnership Admin'])
        ->call('save')
        ->assertHasNoErrors();

    $targetUser->refresh();
    expect($targetUser->hasRole('Partnership Admin'))->toBeTrue();
});

test('admin invites a new employee with roles instead of setting a password directly', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));
    Role::findOrCreate('Partnership Admin');

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('resetForm')
        ->set('name', 'New Employee')
        ->set('email', 'new.employee@example.com')
        ->set('userRoles', ['Partnership Admin'])
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'new.employee@example.com')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('New Employee')
        ->and($newUser->status)->toBe(UserStatus::Invited)
        ->and($newUser->invited_by)->toBe($admin->id)
        ->and($newUser->hasRole('Partnership Admin'))->toBeTrue();

    expect(Employee::where('user_id', $newUser->id)->exists())->toBeTrue();

    Notification::assertSentTo($newUser, UserInvitedNotification::class);
});

test('admin can update an existing employee profile fields', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $targetUser = User::factory()->create(['name' => 'Old Name']);
    $employee = Employee::create(['user_id' => $targetUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('edit', $employee->id)
        ->set('name', 'Updated Name')
        ->set('email', $targetUser->email)
        ->call('save')
        ->assertHasNoErrors();

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Updated Name');
});

test('admin can delete a user, which cascades to their employee profile', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $targetUser = User::factory()->create();
    Employee::create(['user_id' => $targetUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('confirmDelete', $targetUser->id)
        ->call('delete');

    expect(User::find($targetUser->id))->toBeNull();
    expect(Employee::where('user_id', $targetUser->id)->exists())->toBeFalse();
});

test('admin can resend an invite to a user who has not yet activated their account', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $invitedUser = User::factory()->invited()->create();
    Employee::create(['user_id' => $invitedUser->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('resendInvite', $invitedUser->id);

    Notification::assertSentTo($invitedUser, UserInvitedNotification::class);
});

test('admin can suspend an active user and their sessions are invalidated', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));

    $targetUser = User::factory()->create();
    Employee::create(['user_id' => $targetUser->id]);

    DB::table('sessions')->insert([
        'id' => 'test-session-id',
        'user_id' => $targetUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('suspend', $targetUser->id);

    $targetUser->refresh();
    expect($targetUser->status)->toBe(UserStatus::Suspended);
    expect(DB::table('sessions')->where('user_id', $targetUser->id)->exists())->toBeFalse();
});

test('admin cannot suspend themselves', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));
    Employee::create(['user_id' => $admin->id]);

    $this->actingAs($admin);

    Volt::test('master.employees.index')
        ->call('suspend', $admin->id);

    $admin->refresh();
    expect($admin->status)->toBe(UserStatus::Active);
});
