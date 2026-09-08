<?php

use App\Enums\ActivityLogAction;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function logsFor(Model $subject)
{
    return ActivityLog::where('loggable_type', $subject->getMorphClass())
        ->where('loggable_id', $subject->getKey());
}

test('creating a lead records the actor, action, and created data', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('createLead', $contact->id, '2026-08-01', 250_000_000, 'Referral')
        ->assertHasNoErrors();

    $funnel = FoFunnel::where('fo_contact_id', $contact->id)->first();
    $log = logsFor($funnel)->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->action)->toBe(ActivityLogAction::LeadCreated)
        ->and($log->changes['before'] ?? null)->toBeNull()
        ->and($log->changes['after']['sumber'])->toBe('Referral');
});

test('updating a lead records before and after values of only the changed fields', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'sumber' => 'Murni',
        'proyeksi_amount' => 100_000_000,
    ]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('updateLead', $funnel->id, '2026-09-01', 500_000_000, 'SIDS')
        ->assertHasNoErrors();

    $log = logsFor($funnel)->where('action', ActivityLogAction::LeadUpdated)->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($user->id)
        ->and((float) $log->changes['before']['proyeksi_amount'])->toBe(100_000_000.0)
        ->and((float) $log->changes['after']['proyeksi_amount'])->toBe(500_000_000.0)
        ->and($log->changes['before']['sumber'])->toBe('Murni')
        ->and($log->changes['after']['sumber'])->toBe('SIDS');
});

test('deleting a funnel records a snapshot of the data before it is soft deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'leads']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('deleteFunnel', $funnel->id);

    $log = logsFor($funnel)->where('action', ActivityLogAction::LeadDeleted)->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['before']['stage'])->toBe('leads')
        ->and($log->changes['after'] ?? null)->toBeNull();
});

test('moving stage records the previous and new stage', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'leads']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('moveStage', $funnel->id, 'ql');

    $log = logsFor($funnel)->where('action', ActivityLogAction::StageMoved)->first();

    expect($log->changes)->toBe([
        'before' => ['stage' => 'leads'],
        'after' => ['stage' => 'ql'],
    ]);
});

test('submitting akad, won, closing, and lost each record a log entry', function () {
    Storage::fake('s3');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $this->actingAs($admin);

    $akadFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $akadFunnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', UploadedFile::fake()->create('akad.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->call('submitAkad');

    $wonFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    Volt::test('investors.funnel-index')
        ->call('openWonModal', $wonFunnel->id)
        ->set('wonTanggal', '2026-09-01')
        ->set('wonNominal', 400_000_000)
        ->call('submitWon');

    $closingFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    Volt::test('investors.funnel-index')
        ->call('openClosingModal', $closingFunnel->id)
        ->set('closingTanggal', '2026-09-10')
        ->set('closingNominal', 500_000_000)
        ->call('submitClosing');

    $lostFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'pitching']);
    Volt::test('investors.funnel-index')->call('logLostAndMove', $lostFunnel->id, '2026-09-10', 'Tidak tertarik lagi.');

    expect(logsFor($akadFunnel)->where('action', ActivityLogAction::AkadProcessed)->exists())->toBeTrue()
        ->and(logsFor($wonFunnel)->where('action', ActivityLogAction::WonLogged)->exists())->toBeTrue()
        ->and(logsFor($closingFunnel)->where('action', ActivityLogAction::ClosingLogged)->exists())->toBeTrue()
        ->and(logsFor($lostFunnel)->where('action', ActivityLogAction::LostLogged)->exists())->toBeTrue();
});

test('follow up actions each record a log entry against the parent funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-01', 'FU 1', false);

    $activity = $funnel->activities()->where('stage', 'followup')->first();

    Volt::test('investors.funnel-index')->call('updateFollowUp', $activity->id, '2026-08-05', 'FU revisi');
    Volt::test('investors.funnel-index')->call('deleteFollowUp', $activity->id);
    Volt::test('investors.funnel-index')->call('restoreFollowUp', $activity->id);

    $actions = logsFor($funnel)->pluck('action');

    expect($actions)->toContain(ActivityLogAction::FollowUpLogged)
        ->and($actions)->toContain(ActivityLogAction::FollowUpUpdated)
        ->and($actions)->toContain(ActivityLogAction::FollowUpDeleted)
        ->and($actions)->toContain(ActivityLogAction::FollowUpRestored);

    $updateLog = logsFor($funnel)->where('action', ActivityLogAction::FollowUpUpdated)->first();

    expect($updateLog->changes['before']['catatan'])->toBe('FU 1')
        ->and($updateLog->changes['after']['catatan'])->toBe('FU revisi');
});

test('a funnel log timeline survives even after the funnel is force deleted', function () {
    // activity_logs sengaja gak dikasih FK constraint (subjeknya polimorfik, tabelnya beda-beda),
    // jadi riwayatnya tetap harus kebaca walau baris funnel-nya udah beneran hilang dari DB.
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('moveStage', $funnel->id, 'ql');
    expect(logsFor($funnel)->count())->toBe(1);

    $funnel->forceDelete();

    expect(logsFor($funnel)->count())->toBe(1);
});

test('creating a contact records the created data', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('investors.contact-index')
        ->set('nama', 'Budi Santoso')
        ->set('profesi', 'Direktur')
        ->set('no_telepon', '081234567890')
        ->call('saveContact')
        ->assertHasNoErrors();

    $contact = FoContact::where('nama', 'Budi Santoso')->first();
    $log = logsFor($contact)->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->action)->toBe(ActivityLogAction::ContactCreated)
        ->and($log->changes['before'] ?? null)->toBeNull()
        ->and($log->changes['after']['nama'])->toBe('Budi Santoso');
});

test('updating a contact records before and after values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id, 'nama' => 'Nama Lama']);
    $this->actingAs($user);

    Volt::test('investors.contact-index')
        ->call('editContact', $contact->id)
        ->set('nama', 'Nama Baru')
        ->call('saveContact')
        ->assertHasNoErrors();

    $log = logsFor($contact)->where('action', ActivityLogAction::ContactUpdated)->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['before']['nama'])->toBe('Nama Lama')
        ->and($log->changes['after']['nama'])->toBe('Nama Baru');
});

test('deleting a contact records a snapshot of the data before it is soft deleted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id, 'nama' => 'Akan Dihapus']);
    $this->actingAs($user);

    Volt::test('investors.contact-index')->call('deleteContact', $contact->id);

    $log = logsFor($contact)->where('action', ActivityLogAction::ContactDeleted)->first();

    expect($log)->not->toBeNull()
        ->and($log->changes['before']['nama'])->toBe('Akan Dihapus')
        ->and($log->changes['after'] ?? null)->toBeNull();
});
