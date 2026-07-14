<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

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
        ->call('updateLead', $otherFunnel->id, '2026-08-01', 100, 'SIDS')
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

test('can update and delete a funnel entry', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $employee = Employee::create(['user_id' => $user->id]);
    $funnel = FoFunnel::factory()->create(['employee_id' => $employee->id]);
    $this->actingAs($user);

    Volt::test('investors.funnel-index')
        ->call('updateLead', $funnel->id, '2026-09-01', 500_000_000, 'SIDS')
        ->assertHasNoErrors();

    $funnel->refresh();
    expect((float) $funnel->proyeksi_amount)->toBe(500_000_000.0)
        ->and($funnel->sumber)->toBe('SIDS');

    Volt::test('investors.funnel-index')
        ->call('deleteFunnel', $funnel->id);

    expect(FoFunnel::find($funnel->id))->toBeNull();
    expect(FoFunnel::withTrashed()->find($funnel->id))->not->toBeNull();
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
