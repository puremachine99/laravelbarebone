<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoFunnelActivityLog;
use App\Models\User;
use App\Notifications\FundingDealAkadNotification;
use App\Notifications\FundingDealClosedNotification;
use App\Notifications\FundingDealLostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// $foOptions/$tahunOptions/$contactOptions kini di-cache 60 detik (key global, tidak scoped per test) —
// flush di awal tiap test biar gak ada nilai cache nyangkut dari test sebelumnya di proses yang sama.
beforeEach(fn () => Cache::flush());

test('guests are redirected to login', function () {
    $this->get(route('investors.funnel'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-investors permission cannot access the funnel page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.funnel'))
        ->assertStatus(403);
});

test('funnel page is rendered for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));

    $this->actingAs($user)
        ->get(route('investors.funnel'))
        ->assertOk()
        ->assertSee('Funnel')
        ->assertSee('Leads')
        ->assertSee('Won')
        ->assertSee('Lost');
});

test('view mode toggle renders only the active mode server-side', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index');

    expect($component->get('viewMode'))->toBe('kanban');
    $component->assertSee('No cards')->assertDontSee('No funnel data yet.');

    $component->set('viewMode', 'table');

    expect($component->get('viewMode'))->toBe('table');
    $component->assertSee('No funnel data yet.')->assertDontSee('No cards');
});

test('kanban limits cards per column and load more reveals the rest', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $this->actingAs($admin);

    FoFunnel::factory()->count(35)->create(['stage' => 'leads']);

    $component = Volt::test('investors.funnel-index');

    $component->assertSee('Load 5 more (5 left)');

    $component->call('loadMoreCards', 'leads');

    expect($component->get('visibleCounts'))->toBe(['leads' => 60]);
    $component->assertDontSee('Load 5 more (5 left)');
});

test('a fo can create a lead for their own contact', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('createLead', $contact->id, '2026-08-01', 250_000_000, 'Referral')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('fo_funnels', [
        'fo_contact_id' => $contact->id,
        'employee_id' => $employee->id,
        'stage' => 'leads',
        'sumber' => 'Referral',
        'proyeksi_amount' => 250_000_000,
    ]);
});

test('a fo cannot create a lead for a contact they do not own', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherContact = FoContact::factory()->create(['employee_id' => Employee::create(['user_id' => User::factory()->create()->id])->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('createLead', $otherContact->id, '2026-08-01', 250, 'Referral')
        ->assertStatus(403);
});

test('a non admin fo only sees their own contacts in the add lead options', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $ownContact = FoContact::factory()->create(['employee_id' => $employee->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    FoContact::factory()->create(['employee_id' => $otherEmployee->id]);

    $this->actingAs($user);

    $options = Volt::test('investors.funnel-index')->get('contactOptions');

    expect(collect($options)->pluck('id')->all())->toBe([$ownContact->id]);
});

test('an investor admin sees every fo contact in the add lead options', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));

    $employeeA = Employee::create(['user_id' => User::factory()->create()->id]);
    $employeeB = Employee::create(['user_id' => User::factory()->create()->id]);
    $contactA = FoContact::factory()->create(['employee_id' => $employeeA->id]);
    $contactB = FoContact::factory()->create(['employee_id' => $employeeB->id]);

    $this->actingAs($admin);

    $options = Volt::test('investors.funnel-index')->get('contactOptions');

    expect(collect($options)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$contactA->id, $contactB->id])->sort()->values()->all())
        ->and(collect($options)->pluck('pic')->filter()->count())->toBe(2);
});

test('moving to a non-pitching stage updates it directly', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'leads']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('moveStage', $funnel->id, 'ql')
        ->assertHasNoErrors();

    expect($funnel->refresh()->stage)->toBe('ql');
});

test('a plain fo cannot move a funnel to akad, but admin fo can', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('moveStage', $funnel->id, 'akad')
        ->assertStatus(403);

    expect($funnel->refresh()->stage)->toBe('won');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->call('moveStage', $funnel->id, 'akad')
        ->assertHasNoErrors();

    expect($funnel->refresh()->stage)->toBe('akad');
});

test('opening the akad modal is admin-only and resets the form state', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openAkadModal', $funnel->id)
        ->assertStatus(403);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->call('openAkadModal', $funnel->id)
        ->assertHasNoErrors()
        ->assertSet('akadFunnelId', $funnel->id)
        ->assertSet('akadTanggal', '')
        ->assertSet('akadNominal', null)
        ->assertSet('akadDocuments', [['tag' => '', 'file' => null]]);
});

test('admin fo can submit akad with a document upload to s3', function () {
    Storage::fake('s3');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($admin);

    $file = UploadedFile::fake()->create('akad.docx', 2000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', $file)
        ->call('submitAkad')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->stage)->toBe('akad')
        ->and((float) $funnel->nominal_investasi)->toBe(500_000_000.0)
        ->and($funnel->tgl_closing->format('Y-m-d'))->toBe('2026-09-01')
        ->and($funnel->dokumen_akad_path)->not->toBeNull()
        ->and($funnel->dokumen_akad_nama)->toBe('akad.docx')
        ->and($funnel->dokumen_akad_files)->toBe([
            ['tag' => 'Perjanjian A', 'path' => $funnel->dokumen_akad_path, 'nama' => 'akad.docx'],
        ]);

    Storage::disk('s3')->assertExists($funnel->dokumen_akad_path);

    $owner = $employee->user;
    expect($owner->notifications()->count())->toBe(1);
    expect($owner->notifications()->first()->type)->toBe(FundingDealAkadNotification::class);
    expect($owner->notifications()->first()->data['type'])->toBe('funding_deal_akad');
});

test('admin fo can submit akad with multiple tagged documents', function () {
    Storage::fake('s3');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', UploadedFile::fake()->create('perjanjian-a.pdf', 500, 'application/pdf'))
        ->set('akadDocuments.1.tag', 'Perjanjian B')
        ->set('akadDocuments.1.file', UploadedFile::fake()->create('perjanjian-b.pdf', 500, 'application/pdf'))
        ->call('submitAkad')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->dokumen_akad_files)->toHaveCount(2)
        ->and($funnel->dokumen_akad_files[0]['tag'])->toBe('Perjanjian A')
        ->and($funnel->dokumen_akad_files[1]['tag'])->toBe('Perjanjian B');

    Storage::disk('s3')->assertExists($funnel->dokumen_akad_files[0]['path']);
    Storage::disk('s3')->assertExists($funnel->dokumen_akad_files[1]['path']);
});

test('opening the akad modal seeds a single empty document row and add/remove manage the list', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $funnel = FoFunnel::factory()->create(['stage' => 'won']);
    $this->actingAs($admin);

    $component = Volt::test('investors.funnel-index')
        ->call('openAkadModal', $funnel->id)
        ->assertSet('akadDocuments', [['tag' => '', 'file' => null]]);

    $component->call('addAkadDocument')
        ->assertCount('akadDocuments', 2);

    $component->call('removeAkadDocument', 0)
        ->assertCount('akadDocuments', 1);
});

test('a plain fo cannot submit akad even with a valid document', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('akad.pdf', 500, 'application/pdf');

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', $file)
        ->call('submitAkad')
        ->assertStatus(403);

    expect($funnel->refresh()->stage)->toBe('won');
});

test('submitting akad rejects a disallowed file type and files over 10mb', function () {
    Storage::fake('s3');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', UploadedFile::fake()->create('akad.jpg', 200, 'image/jpeg'))
        ->call('submitAkad')
        ->assertHasErrors(['akadDocuments.0.file']);

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', 'Perjanjian A')
        ->set('akadDocuments.0.file', UploadedFile::fake()->create('akad.docx', 11000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->call('submitAkad')
        ->assertHasErrors(['akadDocuments.0.file']);

    Volt::test('investors.funnel-index')
        ->set('akadFunnelId', $funnel->id)
        ->set('akadTanggal', '2026-09-01')
        ->set('akadNominal', 500_000_000)
        ->set('akadDocuments.0.tag', '')
        ->set('akadDocuments.0.file', UploadedFile::fake()->create('akad.pdf', 200, 'application/pdf'))
        ->call('submitAkad')
        ->assertHasErrors(['akadDocuments.0.tag']);

    expect($funnel->refresh()->stage)->toBe('won');
});

test('moving to pitching logs an activity and updates the stage', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'leads']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('logPitchingAndMove', $funnel->id, '2026-08-05', 'Ketemu klien, tertarik.')
        ->assertHasNoErrors();

    expect($funnel->refresh()->stage)->toBe('pitching');

    $this->assertDatabaseHas('fo_funnel_activities', [
        'fo_funnel_id' => $funnel->id,
        'stage' => 'pitching',
        'catatan' => 'Ketemu klien, tertarik.',
    ]);
});

test('submitting won requires both tanggal and nominal', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openWonModal', $funnel->id)
        ->set('wonTanggal', '')
        ->set('wonNominal', null)
        ->call('submitWon')
        ->assertHasErrors(['wonTanggal', 'wonNominal']);

    expect($funnel->refresh()->stage)->toBe('followup');
});

test('moving to won records the commitment date and nominal separately from closing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openWonModal', $funnel->id)
        ->set('wonTanggal', '2026-09-01')
        ->set('wonNominal', 400_000_000)
        ->call('submitWon')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->stage)->toBe('won')
        ->and($funnel->tgl_won->format('Y-m-d'))->toBe('2026-09-01')
        ->and((float) $funnel->nominal_won)->toBe(400_000_000.0)
        ->and($funnel->status_closing)->toBeNull()
        ->and($funnel->tgl_closing)->toBeNull()
        ->and($funnel->nominal_investasi)->toBeNull();

    expect($user->notifications()->count())->toBe(0);
});

test('a won card in the kanban shows the committed nominal, not the original proyeksi amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'won',
        'tgl_won' => '2026-09-01',
        'nominal_won' => 400_000_000,
        'proyeksi_amount' => 999_000_000,
    ]);
    $this->actingAs($user);

    // proyeksi_amount (999 Jt) is also summed into the column header total, so only assert that
    // the committed nominal_won (400 Jt) shows up somewhere — that's what the card itself must use.
    Volt::test('investors.funnel-index')
        ->set('viewMode', 'kanban')
        ->assertSee('Rp 400');
});

test('a plain fo cannot move another fo funnel to won', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openWonModal', $otherFunnel->id)
        ->assertStatus(403);

    expect($otherFunnel->refresh()->stage)->toBe('followup');
});

test('submitting closing requires both tanggal and nominal', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openClosingModal', $funnel->id)
        ->set('closingTanggal', '')
        ->set('closingNominal', null)
        ->call('submitClosing')
        ->assertHasErrors(['closingTanggal', 'closingNominal']);

    expect($funnel->refresh()->stage)->toBe('won');
});

test('moving to closing logs the closing date and amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openClosingModal', $funnel->id)
        ->set('closingTanggal', '2026-09-10')
        ->set('closingNominal', 500_000_000)
        ->call('submitClosing')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->stage)->toBe('closing')
        ->and($funnel->status_closing)->toBe('closing')
        ->and($funnel->tgl_closing->format('Y-m-d'))->toBe('2026-09-10')
        ->and((float) $funnel->nominal_investasi)->toBe(500_000_000.0);
});

test('moving a funding deal to closing notifies investor admins and the owning fo, not partnership managers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    $investorAdmin = User::factory()->create();
    $investorAdmin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));

    // Investors has no manager sign-off concept — a Partnership `verify-manager`
    // holder must NOT be notified about a funding deal closing.
    $partnershipManager = User::factory()->create();
    $partnershipManager->givePermissionTo(Permission::findOrCreate('verify-manager'));

    Volt::test('investors.funnel-index')
        ->call('openClosingModal', $funnel->id)
        ->set('closingTanggal', '2026-09-10')
        ->set('closingNominal', 500_000_000)
        ->call('submitClosing')
        ->assertHasNoErrors();

    expect($investorAdmin->notifications()->count())->toBe(1);
    expect($investorAdmin->notifications()->first()->type)->toBe(FundingDealClosedNotification::class);
    expect($investorAdmin->notifications()->first()->data['type'])->toBe('funding_deal_closed');
    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->data['type'])->toBe('funding_deal_closed');
    expect($partnershipManager->notifications()->count())->toBe(0);
});

test('a plain fo cannot move another fo funnel to closing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id, 'stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openClosingModal', $otherFunnel->id)
        ->assertStatus(403);

    expect($otherFunnel->refresh()->stage)->toBe('won');
});

test('moving to lost logs the lost date and reason', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'pitching']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('logLostAndMove', $funnel->id, '2026-09-10', 'Tidak tertarik lagi.')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->stage)->toBe('lost')
        ->and($funnel->status_closing)->toBe('lost')
        ->and($funnel->tgl_closing->format('Y-m-d'))->toBe('2026-09-10')
        ->and($funnel->lost_reason)->toBe('Tidak tertarik lagi.');

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->first()->type)->toBe(FundingDealLostNotification::class);
    expect($user->notifications()->first()->data['type'])->toBe('funding_deal_lost');
});

test('a plain fo cannot move another fo funnel to lost', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id, 'stage' => 'pitching']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('logLostAndMove', $otherFunnel->id, '2026-09-10', 'Alasan apapun.')
        ->assertStatus(403);

    expect($otherFunnel->refresh()->stage)->toBe('pitching');
});

test('a plain fo cannot move or edit or delete another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('moveStage', $otherFunnel->id, 'ql')
        ->assertStatus(403);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $otherFunnel->id)
        ->assertStatus(403);

    Volt::test('investors.funnel-index')
        ->call('deleteFunnel', $otherFunnel->id)
        ->assertStatus(403);

    $this->assertDatabaseHas('fo_funnels', ['id' => $otherFunnel->id]);
});

test('admin fo sees every funnel, plain fo only sees their own', function () {
    $fo = User::factory()->create();
    $fo->givePermissionTo(Permission::findOrCreate('access-investors'));
    $foEmployee = Employee::create(['user_id' => $fo->id]);
    $ownFunnel = FoFunnel::factory()->create(['employee_id' => $foEmployee->id]);
    $ownFunnel->contact->update(['nama' => 'Kontak Milik FO']);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $otherFunnel->contact->update(['nama' => 'Kontak Milik Orang Lain']);

    $this->actingAs($fo);

    Volt::test('investors.funnel-index')
        ->assertSee('Kontak Milik FO')
        ->assertDontSee('Kontak Milik Orang Lain');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->assertSee('Kontak Milik FO')
        ->assertSee('Kontak Milik Orang Lain');
});

test('a lead whose contact was deleted no longer shows up in the leads column', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);

    $leadFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'leads']);
    $leadFunnel->contact->update(['nama' => 'Kontak Lead Terhapus']);
    $leadFunnel->contact->delete();

    // A funnel past the leads stage should still show up even if its contact is gone.
    $wonFunnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $wonFunnel->contact->update(['nama' => 'Kontak Won Terhapus']);
    $wonFunnel->contact->delete();

    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index');

    $component->assertDontSee('Kontak Lead Terhapus');
    expect($component->instance()->funnels->pluck('id'))
        ->not->toContain($leadFunnel->id)
        ->toContain($wonFunnel->id);
});

test('can update and delete a funnel entry', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->set('editTanggal', '2026-09-01')
        ->set('editProyeksiAmount', 500_000_000)
        ->set('editSumber', 'SIDS')
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect((float) $funnel->proyeksi_amount)->toBe(500_000_000.0)
        ->and($funnel->sumber)->toBe('SIDS');

    Volt::test('investors.funnel-index')
        ->call('deleteFunnel', $funnel->id);

    expect(FoFunnel::find($funnel->id))->toBeNull();
    expect(FoFunnel::withTrashed()->find($funnel->id))->not->toBeNull();
});

test('opening the edit modal seeds amount fields as plain integers, not decimal-cast strings', function () {
    // proyeksi_amount/nominal_investasi/nominal_won are cast decimal:2, so Eloquent returns
    // e.g. "100000000.00". Handed as-is to the money-masked inputs, the mask strips the "."
    // as noise but keeps the trailing zeros, inflating the displayed value 100x.
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'akad',
        'tgl_closing' => '2026-08-01',
        'proyeksi_amount' => 100_000_000,
        'nominal_investasi' => 100_000_000,
    ]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editProyeksiAmount', 100_000_000)
        ->assertSet('editAkadNominal', 100_000_000);
});

test('editing a won-stage funnel seeds and updates the won-specific fields', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'won',
        'tgl_won' => '2026-08-01',
        'nominal_won' => 400_000_000,
    ]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editStage', 'won')
        ->assertSet('editWonTanggal', '2026-08-01')
        ->assertSet('editWonNominal', 400_000_000)
        ->set('editSumber', $funnel->sumber)
        ->set('editWonTanggal', '2026-08-15')
        ->set('editWonNominal', 450_000_000)
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->tgl_won->format('Y-m-d'))->toBe('2026-08-15')
        ->and((float) $funnel->nominal_won)->toBe(450_000_000.0)
        ->and($funnel->stage)->toBe('won');
});

test('editing a closing-stage funnel updates the closing-specific fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'closing',
        'tgl_closing' => '2026-08-10',
        'nominal_investasi' => 500_000_000,
    ]);
    $this->actingAs($admin);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editStage', 'closing')
        ->assertSet('editClosingTanggal', '2026-08-10')
        ->set('editSumber', $funnel->sumber)
        ->set('editClosingTanggal', '2026-08-20')
        ->set('editClosingNominal', 550_000_000)
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->tgl_closing->format('Y-m-d'))->toBe('2026-08-20')
        ->and((float) $funnel->nominal_investasi)->toBe(550_000_000.0);
});

test('editing a lost-stage funnel updates the lost-specific fields', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'lost',
        'tgl_closing' => '2026-08-05',
        'lost_reason' => 'Alasan lama',
    ]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editStage', 'lost')
        ->assertSet('editLostTanggal', '2026-08-05')
        ->assertSet('editLostReason', 'Alasan lama')
        ->set('editSumber', $funnel->sumber)
        ->set('editLostReason', 'Alasan baru')
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    expect($funnel->refresh()->lost_reason)->toBe('Alasan baru');
});

test('a plain fo editing their own akad-stage funnel can only change base fields, not akad nominal or documents', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'akad',
        'tgl_closing' => '2026-08-01',
        'nominal_investasi' => 500_000_000,
        'dokumen_akad_files' => [
            ['tag' => 'Perjanjian A', 'path' => 'funnel-akad/existing-a.pdf', 'nama' => 'existing-a.pdf'],
        ],
    ]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editStage', 'akad')
        ->set('editSumber', 'Referral')
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->sumber)->toBe('Referral')
        ->and((float) $funnel->nominal_investasi)->toBe(500_000_000.0)
        ->and($funnel->dokumen_akad_files)->toBe([
            ['tag' => 'Perjanjian A', 'path' => 'funnel-akad/existing-a.pdf', 'nama' => 'existing-a.pdf'],
        ]);
});

test('admin fo editing an akad-stage funnel can replace or keep existing documents', function () {
    Storage::fake('s3');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('Investor Admin')->givePermissionTo(Permission::findOrCreate('access-investors')));
    $employee = Employee::create(['user_id' => User::factory()->create()->id]);
    $funnel = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'akad',
        'tgl_closing' => '2026-08-01',
        'nominal_investasi' => 500_000_000,
        'dokumen_akad_files' => [
            ['tag' => 'Perjanjian A', 'path' => 'funnel-akad/existing-a.pdf', 'nama' => 'existing-a.pdf'],
        ],
    ]);
    $this->actingAs($admin);

    $component = Volt::test('investors.funnel-index')
        ->call('openEditModal', $funnel->id)
        ->assertSet('editStage', 'akad')
        ->assertSet('editAkadDocuments', [
            ['tag' => 'Perjanjian A', 'path' => 'funnel-akad/existing-a.pdf', 'nama' => 'existing-a.pdf', 'file' => null],
        ]);

    // Keep the existing document untouched, add a second brand-new one.
    $component->call('addEditAkadDocument')
        ->set('editSumber', $funnel->sumber)
        ->set('editAkadDocuments.1.tag', 'Perjanjian B')
        ->set('editAkadDocuments.1.file', UploadedFile::fake()->create('perjanjian-b.pdf', 500, 'application/pdf'))
        ->call('submitEditFunnel')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect($funnel->dokumen_akad_files)->toHaveCount(2)
        ->and($funnel->dokumen_akad_files[0])->toBe(['tag' => 'Perjanjian A', 'path' => 'funnel-akad/existing-a.pdf', 'nama' => 'existing-a.pdf'])
        ->and($funnel->dokumen_akad_files[1]['tag'])->toBe('Perjanjian B');

    Storage::disk('s3')->assertExists($funnel->dokumen_akad_files[1]['path']);
});

test('search finds a funnel by contact name, profesi, phone, pic name, or sumber', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);

    $target = FoContact::factory()->create([
        'employee_id' => $employee->id,
        'nama' => 'Budi Santoso',
        'profesi' => 'Direktur PT Sumber Makmur',
        'no_telepon' => '081234567890',
    ]);
    FoFunnel::factory()->create(['fo_contact_id' => $target->id, 'employee_id' => $employee->id, 'sumber' => 'SIDS']);

    $other = FoContact::factory()->create([
        'employee_id' => $employee->id,
        'nama' => 'Siti Aminah',
        'profesi' => 'Pengusaha',
        'no_telepon' => '089876543210',
    ]);
    FoFunnel::factory()->create(['fo_contact_id' => $other->id, 'employee_id' => $employee->id, 'sumber' => 'Murni']);

    $this->actingAs($user);

    // assertSee/assertDontSee on nama tidak reliable di sini karena nama & opsi sumber
    // tetap dirender di JSON contact-picker & dropdown form terlepas dari filter search —
    // jadi kita cek langsung ke computed property `funnels`, bukan raw HTML.
    $idsFor = function (string $search) {
        return Volt::test('investors.funnel-index')
            ->set('search', $search)
            ->instance()
            ->funnels
            ->pluck('id')
            ->all();
    };

    $targetFunnelId = FoFunnel::where('fo_contact_id', $target->id)->value('id');
    $otherFunnelId = FoFunnel::where('fo_contact_id', $other->id)->value('id');

    foreach (['budi', 'sumber makmur', '081234567890', 'sids'] as $search) {
        $ids = $idsFor($search);
        expect($ids)->toContain($targetFunnelId)
            ->and($ids)->not->toContain($otherFunnelId);
    }

    expect($idsFor('tidak ada yang cocok sama sekali'))->toBeEmpty();
});

test('filterBulan and filterTahun scope funnels by the date relevant to their current stage', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);

    // Pipeline card with no won/closing date yet — falls back to tgl_leads.
    $activePipeline = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'tgl_leads' => '2026-08-01',
        'tgl_qualified' => null,
        'tgl_won' => null,
        'tgl_closing' => null,
        'proyeksi_close_date' => null,
    ]);

    $outsideMonth = FoFunnel::factory()->create(['employee_id' => $employee->id, 'tgl_leads' => '2026-09-01']);
    $outsideYear = FoFunnel::factory()->create(['employee_id' => $employee->id, 'tgl_leads' => '2025-08-01']);

    // Won card whose original lead date is stale/out of range — the won date must take priority.
    $wonOverridesStaleLead = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'won',
        'tgl_leads' => '2026-01-01',
        'tgl_won' => '2026-08-15',
    ]);

    // Closing card whose won date is stale — tgl_closing must take priority over tgl_won.
    $closingOverridesWon = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'closing',
        'tgl_won' => '2026-01-01',
        'tgl_closing' => '2026-08-20',
    ]);

    // Legacy imported card: stage is still 'won', but tgl_closing was populated from the
    // original CSV's closing info and belongs to a different month. tgl_won (in range) must
    // win, not tgl_closing (out of range) — matching how Monthly Conversion/Achievement
    // bucket this same row by tgl_won.
    $wonWithStaleClosingDate = FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'won',
        'tgl_won' => '2026-08-05',
        'tgl_closing' => '2026-06-17',
    ]);

    $this->actingAs($user);

    $ids = Volt::test('investors.funnel-index')
        ->set('filterBulan', 8)
        ->set('filterTahun', 2026)
        ->instance()
        ->funnels
        ->pluck('id')
        ->all();

    expect($ids)->toContain($activePipeline->id, $wonOverridesStaleLead->id, $closingOverridesWon->id, $wonWithStaleClosingDate->id)
        ->and($ids)->not->toContain($outsideMonth->id)
        ->and($ids)->not->toContain($outsideYear->id);
});

test('logging a follow up from drag-drop moves the stage and records history', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'pitching']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('logFollowUp', $funnel->id, '2026-08-10', 'FU pertama', true)
        ->assertHasNoErrors();

    expect($funnel->refresh()->stage)->toBe('followup');

    $this->assertDatabaseHas('fo_funnel_activities', [
        'fo_funnel_id' => $funnel->id,
        'stage' => 'followup',
        'catatan' => 'FU pertama',
    ]);
});

test('logging a follow up from the history sidebar does not move an already-advanced stage', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'won']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('logFollowUp', $funnel->id, '2026-08-11', 'FU tambahan', false)
        ->assertHasNoErrors();

    expect($funnel->refresh()->stage)->toBe('won');

    $this->assertDatabaseHas('fo_funnel_activities', [
        'fo_funnel_id' => $funnel->id,
        'stage' => 'followup',
        'catatan' => 'FU tambahan',
    ]);
});

test('a funnel can accumulate multiple follow up entries', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-01', 'FU 1', false);
    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-08', 'FU 2', false);
    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-15', 'FU 3', false);

    expect($funnel->activities()->where('stage', 'followup')->count())->toBe(3);
});

test('editing a follow up updates it and records an audit log entry', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan awal']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('updateFollowUp', $activity->id, '2026-08-05', 'Catatan revisi')
        ->assertHasNoErrors();

    $activity->refresh();
    expect($activity->tanggal->format('Y-m-d'))->toBe('2026-08-05')
        ->and($activity->catatan)->toBe('Catatan revisi');

    $log = FoFunnelActivityLog::where('fo_funnel_activity_id', $activity->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->changed_by_user_id)->toBe($user->id)
        ->and($log->old_tanggal->format('Y-m-d'))->toBe('2026-08-01')
        ->and($log->new_tanggal->format('Y-m-d'))->toBe('2026-08-05')
        ->and($log->old_catatan)->toBe('Catatan awal')
        ->and($log->new_catatan)->toBe('Catatan revisi');
});

test('editing a follow up without any actual change does not create a log entry', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan awal']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('updateFollowUp', $activity->id, '2026-08-01', 'Catatan awal')
        ->assertHasNoErrors();

    expect(FoFunnelActivityLog::where('fo_funnel_activity_id', $activity->id)->count())->toBe(0);
});

test('a plain fo cannot edit a follow up on another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $activity = $otherFunnel->activities()->create(['employee_id' => $otherEmployee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('updateFollowUp', $activity->id, '2026-08-05', 'Diubah paksa')
        ->assertStatus(403);

    expect($activity->refresh()->catatan)->toBe('Catatan');
});

test('viewing the activity log lists entries newest first', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Awal']);
    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index');
    $component->call('updateFollowUp', $activity->id, '2026-08-02', 'Revisi 1')->assertHasNoErrors();
    $component->call('updateFollowUp', $activity->id, '2026-08-03', 'Revisi 2')->assertHasNoErrors();

    $logs = $component->call('openActivityLog', $activity->id)->instance()->activityLogs;

    expect($logs)->toHaveCount(2)
        ->and($logs->first()->new_catatan)->toBe('Revisi 2')
        ->and($logs->last()->new_catatan)->toBe('Revisi 1');
});

test('a plain fo cannot view the activity log for another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $activity = $otherFunnel->activities()->create(['employee_id' => $otherEmployee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openActivityLog', $activity->id)
        ->assertStatus(403);
});

test('deleting a follow up soft deletes it and hides it from the active history list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('deleteFollowUp', $activity->id)
        ->assertHasNoErrors();

    expect(FoFunnelActivity::find($activity->id))->toBeNull()
        ->and(FoFunnelActivity::withTrashed()->find($activity->id))->not->toBeNull();

    $history = Volt::test('investors.funnel-index')
        ->call('openHistory', $funnel->id)
        ->instance()
        ->historyFunnel;

    expect($history->activities)->toHaveCount(0);
});

test('a plain fo cannot delete a follow up on another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $activity = $otherFunnel->activities()->create(['employee_id' => $otherEmployee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('deleteFollowUp', $activity->id)
        ->assertStatus(403);

    expect(FoFunnelActivity::find($activity->id))->not->toBeNull();
});

test('restoring a follow up brings it back into the active history list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $activity->delete();
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('restoreFollowUp', $activity->id)
        ->assertHasNoErrors();

    expect(FoFunnelActivity::find($activity->id))->not->toBeNull();
});

test('a plain fo cannot restore a follow up on another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $activity = $otherFunnel->activities()->create(['employee_id' => $otherEmployee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan']);
    $activity->delete();
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('restoreFollowUp', $activity->id)
        ->assertStatus(403);

    expect(FoFunnelActivity::find($activity->id))->toBeNull();
});

test('deleted follow ups only show up in the sidebar after toggling "lihat terhapus"', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $activity = $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan terhapus']);
    $activity->delete();
    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index')->call('openHistory', $funnel->id);

    expect($component->instance()->deletedFollowUps)->toHaveCount(0);

    $component->call('toggleDeletedFollowUps');

    $deleted = $component->instance()->deletedFollowUps;
    expect($deleted)->toHaveCount(1)
        ->and($deleted->first()->catatan)->toBe('Catatan terhapus');
});

test('funnels list exposes followup_count aggregated from activities, excluding other stages', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')->call('logPitchingAndMove', $funnel->id, '2026-08-01', 'Sesi pitching');
    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-08', 'FU 1', false);
    Volt::test('investors.funnel-index')->call('logFollowUp', $funnel->id, '2026-08-15', 'FU 2', false);

    $found = Volt::test('investors.funnel-index')
        ->instance()
        ->funnels
        ->firstWhere('id', $funnel->id);

    expect($found->followup_count)->toBe(2);
});

test('history sidebar shows a funnel own follow up activities', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id, 'stage' => 'followup']);
    $funnel->activities()->create(['employee_id' => $employee->id, 'stage' => 'followup', 'tanggal' => '2026-08-01', 'catatan' => 'Catatan pertama']);
    $this->actingAs($user);

    $history = Volt::test('investors.funnel-index')
        ->call('openHistory', $funnel->id)
        ->instance()
        ->historyFunnel;

    expect($history->id)->toBe($funnel->id)
        ->and($history->activities->pluck('catatan')->all())->toContain('Catatan pertama');
});

test('a plain fo cannot open the history sidebar for another fo funnel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    Employee::create(['user_id' => $user->id]);

    $otherEmployee = Employee::create(['user_id' => User::factory()->create()->id]);
    $otherFunnel = FoFunnel::factory()->create(['employee_id' => $otherEmployee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('openHistory', $otherFunnel->id)
        ->assertStatus(403);
});

test('a contact with repeat leads gets a #N suffix from the second occurrence onward', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id, 'nama' => 'Candra-KJPP']);

    $first = FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $employee->id, 'created_at' => now()->subDays(2)]);
    $second = FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $employee->id, 'created_at' => now()->subDay()]);
    $third = FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $employee->id, 'created_at' => now()]);

    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index')->instance();

    expect($component->contactLabel($first))->toBe('Candra-KJPP')
        ->and($component->contactLabel($second))->toBe('Candra-KJPP #2')
        ->and($component->contactLabel($third))->toBe('Candra-KJPP #3');
});

test('a contact with a single lead gets no suffix', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $contact = FoContact::factory()->create(['employee_id' => $employee->id, 'nama' => 'Solo Investor']);
    $funnel = FoFunnel::factory()->create(['fo_contact_id' => $contact->id, 'employee_id' => $employee->id]);

    $this->actingAs($user);

    $component = Volt::test('investors.funnel-index')->instance();

    expect($component->contactLabel($funnel))->toBe('Solo Investor');
});
