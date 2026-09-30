<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use App\Models\User;
use App\Notifications\FoTargetUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeFoEmployee(string $roleName): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();
    $user->givePermissionTo(in_array($roleName, ['Investor Admin', 'Super Admin'], true) ? moduleAccess('access-investors') : moduleViewAccess('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('guests are redirected to login', function () {
    $this->get(route('investors.management-fo'))
        ->assertRedirect(route('login'));
});

test('a plain funding officer can view management fo but not the target form', function () {
    $employee = makeFoEmployee('Funding Officer');
    $this->actingAs($employee->user);

    Volt::test('investors.management-fo')
        ->assertOk()
        ->assertSee('FO Management')
        ->assertDontSee('Save Target');
});

test('a plain funding officer cannot save or reset a target', function () {
    $employee = makeFoEmployee('Funding Officer');
    $this->actingAs($employee->user);

    Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $employee->id)
        ->set('targetInputs.leads', '999')
        ->call('saveTargets')
        ->assertStatus(403);

    $this->assertDatabaseMissing('fo_targets', ['employee_id' => $employee->id, 'metric_key' => 'leads']);
});

test('investor admin can access management fo and sees the fo list', function () {
    $admin = makeFoEmployee('Investor Admin');
    makeFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->assertOk()
        ->assertSee('FO Management')
        ->assertSee('Leads')
        ->assertSee('Deal Value');
});

test('super admin can access management fo', function () {
    $admin = makeFoEmployee('Super Admin');
    $this->actingAs($admin->user);

    $this->get(route('investors.management-fo'))->assertOk();
});

test('direksi can access management fo and sees the fo ranking', function () {
    $direksi = makeFoEmployee('Direksi');
    makeFoEmployee('Funding Officer');
    $this->actingAs($direksi->user);

    Volt::test('investors.management-fo')
        ->assertOk()
        ->assertSee('FO Ranking This Year');
});

test('a plain funding officer only sees their own data and not the fo ranking', function () {
    $fo = makeFoEmployee('Funding Officer');
    $otherFo = makeFoEmployee('Funding Officer');
    $this->actingAs($fo->user);

    $component = Volt::test('investors.management-fo')
        ->assertOk()
        ->assertSee($fo->user->name)
        ->assertDontSee($otherFo->user->name)
        ->assertDontSee('FO Ranking This Year');

    expect(collect($component->instance()->foEmployees())->pluck('id')->all())->toBe([$fo->id]);
});

test('investor admin, super admin, and direksi see every fo in the list', function () {
    $admin = makeFoEmployee('Investor Admin');
    $fo = makeFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')->assertOk();

    expect(collect($component->instance()->foEmployees())->pluck('id')->all())
        ->toEqualCanonicalizing([$admin->id, $fo->id]);
});

test('updating a yearly target persists it to the database and recalculates the split', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan')
        ->call('updateYearlyTarget', $admin->id, 'leads', 120)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_targets', [
        'employee_id' => $admin->id,
        'year' => now()->year,
        'metric_key' => 'leads',
        'yearly_target' => 120.00,
    ]);

    expect((float) $component->instance()->yearlyTargetFor($admin->id, 'leads'))->toBe(120.0);

    $component->set('activePeriod', 'bulanan');

    expect((float) $component->instance()->targetFor($admin->id, 'leads'))->toBe(10.0);
});

test('editing the target from the bulanan tab converts the input back into a yearly target', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->set('activePeriod', 'bulanan')
        ->set('targetInputs.leads', '10')
        ->call('saveTargets')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_targets', [
        'employee_id' => $admin->id,
        'metric_key' => 'leads',
        'yearly_target' => 120.00,
    ]);

    expect((float) $component->instance()->yearlyTargetFor($admin->id, 'leads'))->toBe(120.0);
});

test('editing the target from the harian tab converts the input back into a yearly target', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->set('activePeriod', 'harian')
        ->set('targetInputs.leads', '2')
        ->call('saveTargets')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_targets', [
        'employee_id' => $admin->id,
        'metric_key' => 'leads',
        'yearly_target' => 600.00, // 2 leads/hari * 25 hari kerja * 12 bulan
    ]);
});

test('editing a currency target from the bulanan tab converts the masked input back into a yearly target', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->set('activePeriod', 'bulanan')
        ->set('targetInputs.nilai_closing', '100.000.000')
        ->call('saveTargets')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_targets', [
        'employee_id' => $admin->id,
        'metric_key' => 'nilai_closing',
        'yearly_target' => 1_200_000_000.00,
    ]);
});

test('switching period tabs reloads the target input buffer with the split value', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoTarget::create(['employee_id' => $admin->id, 'year' => now()->year, 'metric_key' => 'leads', 'yearly_target' => 120]);

    $component = Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->set('activePeriod', 'bulanan');

    expect($component->get('targetInputs.leads'))->toBe('10');

    $component->set('activePeriod', 'harian');

    expect($component->get('targetInputs.leads'))->toBe('0.4');
});

test('save targets only persists metrics whose buffered input actually changed', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $component = Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->set('targetInputs.leads', '120')
        ->call('saveTargets')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_targets', [
        'employee_id' => $admin->id,
        'metric_key' => 'leads',
        'yearly_target' => 120.00,
    ]);

    $targetChangeLogs = fn () => ActivityLog::where('action', ActivityLogAction::TargetUpdated)
        ->where('changes->after->employee_id', $admin->id)
        ->where('changes->after->metric_key', 'leads')
        ->count();

    expect($targetChangeLogs())->toBe(1);

    // Submit lagi tanpa mengubah nilai apa pun — gak boleh nambah log baru.
    $component->call('saveTargets')->assertHasNoErrors();

    expect($targetChangeLogs())->toBe(1);
});

test('saving a changed target notifies the owning fo with the old and new values', function () {
    $admin = makeFoEmployee('Investor Admin');
    $fo = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoTarget::create(['employee_id' => $fo->id, 'year' => now()->year, 'metric_key' => 'leads', 'yearly_target' => 100]);
    FoTarget::create(['employee_id' => $fo->id, 'year' => now()->year, 'metric_key' => 'ql', 'yearly_target' => 80]);

    Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $fo->id)
        ->set('targetInputs.leads', '194')
        ->set('targetInputs.ql', '126')
        ->call('saveTargets')
        ->assertHasNoErrors();

    expect($fo->user->notifications()->count())->toBe(1);
    $notification = $fo->user->notifications()->first();
    expect($notification->type)->toBe(FoTargetUpdatedNotification::class);
    expect($notification->data['type'])->toBe('fo_target_updated');
    expect($notification->data['message'])->toContain('Leads 100 → 194');
    expect($notification->data['message'])->toContain('QL 80 → 126');
    expect($admin->user->notifications()->count())->toBe(0);
});

test('saving targets with no actual change does not notify the fo', function () {
    $admin = makeFoEmployee('Investor Admin');
    $fo = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $fo->id)
        ->call('saveTargets')
        ->assertHasNoErrors();

    expect($fo->user->notifications()->count())->toBe(0);
});

test('updating a yearly target rejects a negative value', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    Volt::test('investors.management-fo')
        ->call('updateYearlyTarget', $admin->id, 'leads', -5)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('fo_targets', ['employee_id' => $admin->id]);
});

test('yearly target falls back to a historical-data suggestion when nothing is saved yet', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    // 10 leads spread across the last 180 hari — cukup buat dihitung annualized run-rate-nya.
    FoFunnel::factory()->count(10)->create([
        'employee_id' => $admin->id,
        'tgl_leads' => now()->subDays(180)->toDateString(),
    ]);

    $suggested = Volt::test('investors.management-fo')
        ->instance()
        ->yearlyTargetFor($admin->id, 'leads');

    // annualizeFactor = 365/180, jadi 10 leads -> ~20.
    expect($suggested)->toBe(20.0);

    FoTarget::create([
        'employee_id' => $admin->id,
        'year' => now()->year,
        'metric_key' => 'leads',
        'yearly_target' => 999,
    ]);

    $saved = Volt::test('investors.management-fo')
        ->instance()
        ->yearlyTargetFor($admin->id, 'leads');

    expect($saved)->toBe(999.0);
});

test('updating a yearly target writes an audit log with who, whose, and old/new values', function () {
    $admin = makeFoEmployee('Investor Admin');
    $target = makeFoEmployee('Funding Officer');
    $this->actingAs($admin->user);

    // Pertama kali diisi — belum ada nilai lama tersimpan (masih saran otomatis).
    Volt::test('investors.management-fo')
        ->call('updateYearlyTarget', $target->id, 'leads', 100)
        ->assertHasNoErrors();

    $log = ActivityLog::where('action', ActivityLogAction::TargetUpdated)
        ->where('changes->after->employee_id', $target->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($admin->user->id)
        ->and($log->changes['before'] ?? null)->toBeNull()
        ->and((float) $log->changes['after']['yearly_target'])->toBe(100.0);

    // Diubah lagi — kali ini ada nilai lama (100) yang harus kecatat sebagai before.
    Volt::test('investors.management-fo')
        ->call('updateYearlyTarget', $target->id, 'leads', 150)
        ->assertHasNoErrors();

    $log = ActivityLog::where('action', ActivityLogAction::TargetUpdated)
        ->where('changes->after->employee_id', $target->id)
        ->latest('id')
        ->first();

    expect($log->causer_id)->toBe($admin->user->id)
        ->and((float) $log->changes['before']['yearly_target'])->toBe(100.0)
        ->and((float) $log->changes['after']['yearly_target'])->toBe(150.0);

    expect(ActivityLog::where('action', ActivityLogAction::TargetUpdated)
        ->where(fn ($q) => $q->where('changes->after->employee_id', $target->id)
            ->orWhere('changes->before->employee_id', $target->id))
        ->count())->toBe(2);
});

test('resetting a yearly target logs the old value with a null new value', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoTarget::create(['employee_id' => $admin->id, 'year' => now()->year, 'metric_key' => 'leads', 'yearly_target' => 999]);

    Volt::test('investors.management-fo')
        ->call('resetYearlyTarget', $admin->id, 'leads')
        ->assertHasNoErrors();

    $log = ActivityLog::where('action', ActivityLogAction::TargetUpdated)
        ->where('changes->before->employee_id', $admin->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($admin->user->id)
        ->and((float) $log->changes['before']['yearly_target'])->toBe(999.0)
        ->and($log->changes['after'] ?? null)->toBeNull();
});

test('resetting a yearly target deletes the override and falls back to the suggestion again', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoTarget::create(['employee_id' => $admin->id, 'year' => now()->year, 'metric_key' => 'leads', 'yearly_target' => 999]);

    Volt::test('investors.management-fo')
        ->call('resetYearlyTarget', $admin->id, 'leads')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('fo_targets', ['employee_id' => $admin->id, 'metric_key' => 'leads']);
});

test('monthly rekap returns six months of real realisasi data for the selected fo', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->startOfMonth()->toDateString()]);
    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->subMonths(2)->startOfMonth()->toDateString()]);

    $rekap = Volt::test('investors.management-fo')
        ->set('targetSettingEmployeeId', $admin->id)
        ->instance()
        ->monthlyRekap();

    expect($rekap)->toHaveCount(6)
        ->and(collect($rekap)->sum('leads'))->toBe(2);
});

test('fo ranking sorts by nilai closing this year and computes achievement against target', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'closing',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 100_000_000,
    ]);
    FoTarget::create(['employee_id' => $admin->id, 'year' => now()->year, 'metric_key' => 'nilai_closing', 'yearly_target' => 200_000_000]);

    $ranking = Volt::test('investors.management-fo')
        ->instance()
        ->foRanking();

    $row = collect($ranking)->firstWhere('name', $admin->user->name);

    expect($row['nilai_closing'])->toBe(100_000_000.0)
        ->and($row['achievement'])->toBe(50.0);
});

test('realisasi leads and ql are counted from real funnel data within the active period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->toDateString(), 'tgl_qualified' => null]);
    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->toDateString(), 'tgl_qualified' => now()->toDateString()]);
    FoFunnel::factory()->create(['employee_id' => $admin->id, 'tgl_leads' => now()->subYear()->toDateString()]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'leads'))->toBe(2)
        ->and($component->instance()->realisasiFor($admin->id, 'ql'))->toBe(1);
});

test('realisasi closing and nilai closing only count closing/akad funnels closed within the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'closing',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 500_000_000,
    ]);
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'akad',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 250_000_000,
    ]);
    // Won cuma komitmen (belum ada uang masuk) — gak boleh ikut kehitung.
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'won',
        'tgl_closing' => now()->toDateString(),
        'nominal_investasi' => 999_000_000,
    ]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'closing'))->toBe(2)
        ->and($component->instance()->realisasiFor($admin->id, 'nilai_closing'))->toBe(750_000_000.0);
});

test('proyeksi counts funnels still in pipeline with a proyeksi close date in the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'pitching',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 300_000_000,
    ]);
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'followup',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 200_000_000,
    ]);
    // Udah lost — gak boleh ikut jadi proyeksi.
    FoFunnel::factory()->create([
        'employee_id' => $admin->id,
        'stage' => 'lost',
        'proyeksi_close_date' => now()->toDateString(),
        'proyeksi_amount' => 999_000_000,
    ]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->proyeksiFor($admin->id, 'closing'))->toBe(2)
        ->and($component->instance()->proyeksiFor($admin->id, 'nilai_closing'))->toBe(500_000_000.0)
        ->and($component->instance()->proyeksiFor($admin->id, 'leads'))->toBeNull();
});

test('realisasi pitching counts distinct funnels with a pitching activity in the period', function () {
    $admin = makeFoEmployee('Investor Admin');
    $this->actingAs($admin->user);

    $contact = FoContact::factory()->create(['employee_id' => $admin->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $admin->id, 'fo_contact_id' => $contact->id, 'stage' => 'pitching']);

    // Dua aktivitas pitching di funnel yang SAMA — cuma dihitung 1 funnel, bukan 2.
    FoFunnelActivity::factory()->create(['fo_funnel_id' => $funnel->id, 'stage' => 'pitching', 'tanggal' => now()->toDateString()]);
    FoFunnelActivity::factory()->create(['fo_funnel_id' => $funnel->id, 'stage' => 'pitching', 'tanggal' => now()->toDateString()]);

    $component = Volt::test('investors.management-fo')
        ->set('activePeriod', 'tahunan');

    expect($component->instance()->realisasiFor($admin->id, 'pitching'))->toBe(1);
});
