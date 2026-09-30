<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'susan.suci@example.com']);
    $this->employee = Employee::create(['user_id' => $this->user->id]);
});

test('dry-run does not change anything', function () {
    $contact = FoContact::factory()->create(['employee_id' => null, 'created_at' => '2026-08-10']);

    $this->artisan('fo:reassign-orphans', ['email' => 'susan.suci@example.com', '--from' => '2026-08-01', '--until' => '2026-08-31'])
        ->assertSuccessful();

    expect($contact->fresh()->employee_id)->toBeNull();
});

test('execute reassigns only orphans inside the date window', function () {
    $inside = FoContact::factory()->create(['employee_id' => null, 'created_at' => '2026-08-10']);
    $outside = FoContact::factory()->create(['employee_id' => null, 'created_at' => '2026-09-20']);
    $owned = FoContact::factory()->create(['employee_id' => Employee::create(['user_id' => User::factory()->create()->id])->id, 'created_at' => '2026-08-10']);
    $funnel = FoFunnel::factory()->create(['fo_contact_id' => $inside->id, 'employee_id' => null, 'created_at' => '2026-08-11']);

    $this->artisan('fo:reassign-orphans', ['email' => 'susan.suci@example.com', '--from' => '2026-08-01', '--until' => '2026-08-31', '--execute' => true])
        ->assertSuccessful();

    expect($inside->fresh()->employee_id)->toBe($this->employee->id)
        ->and($funnel->fresh()->employee_id)->toBe($this->employee->id)
        ->and($outside->fresh()->employee_id)->toBeNull()
        ->and($owned->fresh()->employee_id)->not->toBe($this->employee->id);
});

test('window options are required', function () {
    $this->artisan('fo:reassign-orphans', ['email' => 'susan.suci@example.com'])->assertFailed();
});

test('unknown email fails', function () {
    $this->artisan('fo:reassign-orphans', ['email' => 'nobody@example.com', '--from' => '2026-08-01', '--until' => '2026-08-31'])->assertFailed();
});
