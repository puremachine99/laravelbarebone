<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('investors.activity-log'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.activity-log'))
        ->assertStatus(403);
});

test('activity log page is rendered for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));

    $this->actingAs($user)
        ->get(route('investors.activity-log'))
        ->assertOk()
        ->assertSee('Activity Log')
        ->assertSee('Beta');
});

test('a plain fo only sees log entries for their own data', function () {
    $fo = User::factory()->create();
    $fo->givePermissionTo(moduleAccess('access-investors'));
    $foEmployee = Employee::create(['user_id' => $fo->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);

    $ownFunnel = FoFunnel::factory()->create(['employee_id' => $foEmployee->id, 'stage' => 'leads']);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id, 'stage' => 'leads']);

    ActivityLog::record($ownFunnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);
    ActivityLog::record($otherFunnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);

    $this->actingAs($fo);

    $logs = Volt::test('investors.activity-log-index')->instance()->logs;

    expect($logs->pluck('loggable_id'))->toContain($ownFunnel->id)
        ->and($logs->pluck('loggable_id'))->not->toContain($otherFunnel->id);
});

test('an investor admin sees log entries for every fo', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(moduleAccess('access-investors')));

    $foEmployeeA = Employee::create(['user_id' => User::factory()->create()->id]);
    $foEmployeeB = Employee::create(['user_id' => User::factory()->create()->id]);

    $funnelA = FoFunnel::factory()->create(['employee_id' => $foEmployeeA->id]);
    $funnelB = FoFunnel::factory()->create(['employee_id' => $foEmployeeB->id]);

    ActivityLog::record($funnelA, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);
    ActivityLog::record($funnelB, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);

    $this->actingAs($admin);

    $logs = Volt::test('investors.activity-log-index')->instance()->logs;

    expect($logs->pluck('loggable_id'))->toContain($funnelA->id)
        ->and($logs->pluck('loggable_id'))->toContain($funnelB->id);
});

test('filtering by actor scopes the results to that causer only', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(moduleAccess('access-investors')));

    $actingFo = User::factory()->create();
    $foEmployee = Employee::create(['user_id' => $actingFo->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $foEmployee->id]);

    $otherFoEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherFoEmployee->id]);

    $this->actingAs($actingFo);
    ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);

    $this->actingAs($otherFoEmployee->user);
    ActivityLog::record($otherFunnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);

    $this->actingAs($admin);

    $logs = Volt::test('investors.activity-log-index')
        ->set('filterActor', $actingFo->id)
        ->instance()
        ->logs;

    expect($logs->pluck('causer_id')->unique()->all())->toBe([$actingFo->id]);
});

test('actor options include super admins even without a finance department', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(moduleAccess('access-investors')));

    $superAdmin = User::factory()->create(['name' => 'Super Admin Nakama']);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));
    Employee::create(['user_id' => $superAdmin->id]);

    $this->actingAs($admin);

    $options = Volt::test('investors.activity-log-index')->instance()->actorOptions;

    expect(collect($options)->pluck('id'))->toContain($superAdmin->id);
});

test('filtering by month and year scopes the results to that period', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    $julyLog = ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);
    $julyLog->forceFill(['created_at' => '2026-07-15 10:00:00'])->save();

    $augustLog = ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'ql'], ['stage' => 'pitching']);
    $augustLog->forceFill(['created_at' => '2026-08-15 10:00:00'])->save();

    $logs = Volt::test('investors.activity-log-index')
        ->set('filterBulan', 7)
        ->set('filterTahun', 2026)
        ->instance()
        ->logs;

    expect($logs->pluck('id'))->toContain($julyLog->id)
        ->and($logs->pluck('id'))->not->toContain($augustLog->id);
});

test('filtering by time range scopes the results to that window', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    $morningLog = ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);
    $morningLog->forceFill(['created_at' => '2026-08-15 08:00:00'])->save();

    $eveningLog = ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'ql'], ['stage' => 'pitching']);
    $eveningLog->forceFill(['created_at' => '2026-08-15 20:00:00'])->save();

    $logs = Volt::test('investors.activity-log-index')
        ->set('filterTimeStart', '2026-08-15T07:00')
        ->set('filterTimeEnd', '2026-08-15T12:00')
        ->instance()
        ->logs;

    expect($logs->pluck('id'))->toContain($morningLog->id)
        ->and($logs->pluck('id'))->not->toContain($eveningLog->id);
});

test('the page exposes no mutating actions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $this->actingAs($user);

    $component = Volt::test('investors.activity-log-index');

    foreach (['createLead', 'deleteFunnel', 'moveStage', 'saveContact', 'deleteContact'] as $method) {
        expect(method_exists($component->instance(), $method))->toBeFalse();
    }
});

test('array change values (e.g. multi-file uploads) are rendered without error', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    ActivityLog::record(
        $funnel,
        ActivityLogAction::StageMoved,
        ['dokumen_akad' => ['akad-1.pdf']],
        ['dokumen_akad' => ['akad-1.pdf', 'akad-2.pdf']],
    );

    Volt::test('investors.activity-log-index')
        ->assertOk()
        ->assertSee('akad-1.pdf');
});

test('subject and owner labels resolve correctly for a funnel log', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(moduleAccess('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id, 'nama' => 'Budi Santoso']);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'fo_contact_id' => $contact->id]);
    $this->actingAs($user);

    ActivityLog::record($funnel, ActivityLogAction::StageMoved, ['stage' => 'leads'], ['stage' => 'ql']);

    Volt::test('investors.activity-log-index')
        ->assertSee('Budi Santoso')
        ->assertSee($employee->user->name);
});
