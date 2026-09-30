<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('employee detail drawer shows who invited the user and when', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(moduleAccess('access-internal-org'));
    $this->actingAs($admin);

    $inviter = User::factory()->create(['name' => 'Inviting Admin']);
    $invitedAt = now()->subDays(3);

    $employeeUser = User::factory()->create([
        'invited_by' => $inviter->id,
        'invited_at' => $invitedAt,
    ]);
    $employee = Employee::create(['user_id' => $employeeUser->id]);

    Volt::test('master.employees.index')
        ->call('showDetail', $employee->id)
        ->assertSee('Inviting Admin')
        ->assertSee($invitedAt->format('d M Y'));
});
