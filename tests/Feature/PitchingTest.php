<?php

use App\Models\Card;
use App\Models\Contact;
use App\Models\Division;
use App\Models\Opd;
use App\Models\Output;
use App\Models\PipelineProfile;
use App\Models\PipelineStage;
use App\Models\ProductCategory;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\ServiceCenter;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

test('guests are redirected to login', function () {
    $this->get(route('partnership.pitching'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-partnership permission cannot access pitching page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('partnership.pitching'))
        ->assertStatus(403);
});

test('authorized users with access-partnership permission can access pitching page', function () {
    $user = User::factory()->create();
    $permission = moduleAccess('access-partnership');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('partnership.pitching'))
        ->assertStatus(200)
        ->assertSeeLivewire('partnership.pitching-index');
});

test('pitching page livewire component functionality', function () {
    $user = User::factory()->create();
    $permission = moduleAccess('access-partnership');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    // Seed pipeline stages
    (new PipelineSeeder)->run();

    // Ensure database records exist for cascading dropdowns and products
    $region = Region::first() ?: Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $province = Province::first() ?: Province::create(['name' => 'Jawa Timur', 'region_id' => $region->id]);
    $regency = Regency::first() ?: Regency::create(['name' => 'Kabupaten Malang', 'province_id' => $province->id]);
    $opd = Opd::first() ?: Opd::create(['name' => 'Dinas Kesehatan', 'regency_id' => $regency->id]);

    $div = Division::first() ?: Division::create(['code' => 'DIV-1', 'name' => 'Division 1']);
    $sc = ServiceCenter::first() ?: ServiceCenter::create(['division_id' => $div->id, 'code' => 'SC-1', 'full_code' => 'SC-FC-1', 'name' => 'Service Center 1']);
    $cat = ProductCategory::first() ?: ProductCategory::create(['code' => 'CAT-1', 'name' => 'Category 1']);
    $product = Output::first() ?: Output::create([
        'division_id' => $div->id,
        'service_center_id' => $sc->id,
        'category_code' => $cat->code,
        'output_name' => 'Aplikasi SmartID v3',
        'pelaksana' => 'SmartID',
        'status' => true,
        'sequence' => 1,
        'generated_code' => 'OUT-1',
    ]);

    // Create a contact
    $contact = Contact::create([
        'name' => 'PIC Budi',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
        'regency_id' => $regency->id,
        'opd_id' => $opd->id,
    ]);

    // Test card creation in pitching-create-modal component
    Livewire::test('partnership.pitching-create-modal')
        ->assertSet('showCreateModal', false)
        ->set('selectedProvinceId', $province->id)
        ->set('selectedRegencyId', $regency->id)
        ->set('selectedOpdId', $opd->id)
        ->set('selectedContactId', $contact->id)
        ->set('newProduct', $product->id)
        ->set('sumberPotensi', 'SIRUP')
        ->set('newBudget', '50000000')
        ->set('newDate', date('Y-m-d', strtotime('+1 day')))
        ->set('newMethod', 'Online')
        ->set('terlaksana', false)
        ->call('addCard')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false)
        ->assertDispatched('card-added');

    $card = Card::whereHas('contact', fn ($q) => $q->where('name', 'PIC Budi'))->first();
    expect($card)->not->toBeNull();

    // Mark card as manager verified so it can transition to Terlaksana
    $card->update(['manager_verified' => true]);

    // Test card movement and decisions on pitching-index
    $test = Livewire::test('partnership.pitching-index')
        ->assertSet('filterFiscalYear', (string) date('Y'))
        // Move card to 'Terlaksana' (should open terlaksana modal)
        ->call('moveCard', $card->id, 'Terlaksana')
        ->assertSet('showTerlaksanaModal', true)
        ->assertSet('pendingDecisionCard.id', $card->id)
        // Save Positive Decision
        ->set('decisionType', 'won')
        ->set('projectionMonth', date('Y-m'))
        ->set('projectionWeek', 'Pekan 2')
        ->set('driveLink', 'https://drive.google.com/test')
        ->call('saveTerlaksanaDecision')
        ->assertSet('showWonConfirmModal', true)
        ->call('confirmWonAction')
        ->assertSet('showTerlaksanaModal', false);

    // Verify card is now won
    $card->refresh();
    expect($card->status)->toBe('won');

    // Reset status back to Rencana to test Tidak Terlaksana
    $test->call('moveCard', $card->id, 'Rencana');
    $card->refresh();
    expect($card->stage->stage_name)->toBe('Rencana');

    // Move to Tidak Terlaksana (should open tidak terlaksana modal)
    $test->call('moveCard', $card->id, 'Tidak Terlaksana')
        ->assertSet('showTidakTerlaksanaModal', true)
        ->set('tidakTerlaksanaReason', 'reschedule - bureaucracy')
        ->set('rescheduleOption', 'next_week')
        ->call('saveTidakTerlaksanaDecision')
        ->assertSet('showTidakTerlaksanaModal', false);

    // Verify original card is updated to Tidak Terlaksana stage and status is lost
    $card->refresh();
    expect($card->stage->stage_name)->toBe('Tidak Terlaksana')
        ->and($card->status)->toBe('lost')
        ->and($card->negative_reason)->toBe('reschedule - bureaucracy');

    // Verify duplicate card is created under Rencana
    $duplicateCard = Card::where('stage_id', PipelineStage::where('stage_name', 'Rencana')->first()->id)
        ->where('id', '!=', $card->id)
        ->first();
    expect($duplicateCard)->not->toBeNull()
        ->and($duplicateCard->status)->toBe('Active');
});

test('contact QL status condition', function () {
    (new PipelineSeeder)->run();
    $region = Region::first() ?: Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact = Contact::create([
        'name' => 'PIC Charlie',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
    ]);

    // Initially contact is not QL
    expect($contact->is_ql)->toBeFalse();

    $pitchingProfile = PipelineProfile::where('pipeline_name', 'Pitching')->first();
    $rencanaStage = $pitchingProfile->stages()->where('stage_name', 'Rencana')->first();

    // Add card with nominal 0 -> still not QL
    $card = Card::create([
        'title' => 'Product A',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact->id,
        'nominal_value' => 0,
        'status' => 'Active',
    ]);
    expect($contact->refresh()->is_ql)->toBeFalse();

    // Update nominal > 0 -> should be QL
    $card->update(['nominal_value' => 1000000]);
    expect($contact->refresh()->is_ql)->toBeTrue();
});

test('pmo user verify manager permission checks', function () {
    (new PipelineSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo('access-partnership');

    // Without verify-manager permission
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->assertSet('canVerify', false);

    // With verify-manager permission
    $user->givePermissionTo('verify-manager');
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->assertSet('canVerify', true);
});

test('pitching won requires manager verification but hot or warm do not', function () {
    (new PipelineSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo(['access-partnership']); // no verify-manager

    $region = Region::first() ?: Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact = Contact::create([
        'name' => 'PIC Delta',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
    ]);

    $pitchingProfile = PipelineProfile::where('pipeline_name', 'Pitching')->first();
    $rencanaStage = $pitchingProfile->stages()->where('stage_name', 'Rencana')->first();

    $card = Card::create([
        'title' => 'Product B',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact->id,
        'nominal_value' => 15000000,
        'status' => 'Active',
        'manager_verified' => false,
    ]);

    // Trying to save decision as won should show error since manager_verified is false
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->call('moveCard', $card->id, 'Terlaksana')
        ->set('decisionType', 'won')
        ->set('projectionMonth', date('Y-m'))
        ->set('projectionWeek', 'Pekan 1')
        ->call('saveTerlaksanaDecision');

    $card->refresh();
    expect($card->status)->not->toBe('won');

    // Trying to save as hot should succeed even if manager_verified is false
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->call('moveCard', $card->id, 'Terlaksana')
        ->set('decisionType', 'hot')
        ->set('projectionMonth', date('Y-m'))
        ->set('projectionWeek', 'Pekan 1')
        ->call('saveTerlaksanaDecision');

    $card->refresh();
    expect($card->status)->toBe('hot');

    // Save edit when manager_verified is false
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->call('editCard', $card->id)
        ->set('editTitle', 'New Title')
        ->set('editBudget', 20000000)
        ->set('editStatus', 'hot')
        ->call('saveEdit');
    $card->refresh();
    expect($card->title)->toBe('New Title');

    // Make verified
    $card->update(['manager_verified' => true]);

    // Now saving won status works since manager_verified is true
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->call('moveCard', $card->id, 'Terlaksana')
        ->set('decisionType', 'won')
        ->set('projectionMonth', date('Y-m'))
        ->set('projectionWeek', 'Pekan 1')
        ->call('saveTerlaksanaDecision')
        ->call('confirmWonAction');
    $card->refresh();
    expect($card->status)->toBe('won')
        ->and($card->history_logs)->toBeArray()
        ->and(count($card->history_logs))->toBeGreaterThan(0)
        ->and($card->history_logs[count($card->history_logs) - 1]['action'])->toBe('stage_changed');
});

test('pitching page filter and search functionality works', function () {
    (new PipelineSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo(['access-partnership']);

    $region = Region::first() ?: Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact1 = Contact::create(['name' => 'Instansi Alpha', 'sumber' => 'SIRUP', 'region_id' => $region->id, 'category' => 'Pemerintah Daerah']);
    $contact2 = Contact::create(['name' => 'Instansi Beta', 'sumber' => 'SIPD', 'region_id' => $region->id, 'category' => 'Pemerintah Daerah']);

    $pitchingProfile = PipelineProfile::where('pipeline_name', 'Pitching')->first();
    $rencanaStage = $pitchingProfile->stages()->where('stage_name', 'Rencana')->first();

    $card1 = Card::create([
        'title' => 'Product Alpha',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact1->id,
        'nominal_value' => 10000000,
        'status' => 'warm',
        'sumber_proyeksi' => 'SIRUP',
        'manager_verified' => false,
    ]);

    $card2 = Card::create([
        'title' => 'Product Beta',
        'stage_id' => $rencanaStage->id,
        'contact_id' => $contact2->id,
        'nominal_value' => 20000000,
        'status' => 'hot',
        'sumber_proyeksi' => 'SIPD',
        'manager_verified' => true,
    ]);

    // Test search filter
    Livewire::actingAs($user)
        ->test('partnership.pitching-index')
        ->set('search', 'Alpha')
        ->assertSee('Instansi Alpha')
        ->assertDontSee('Instansi Beta')
        // Test fiscal year filter
        ->set('search', '')
        ->set('filterSumberProyeksi', 'SIRUP')
        ->assertSee('Instansi Alpha')
        ->assertDontSee('Instansi Beta')
        // Test stage filter
        ->set('filterSumberProyeksi', '')
        ->set('filterStageId', $rencanaStage->id)
        ->assertSee('Instansi Alpha')
        ->assertSee('Instansi Beta')
        // Test status filter
        ->set('filterStatus', 'hot')
        ->assertDontSee('Instansi Alpha')
        ->assertSee('Instansi Beta')
        // Test manager verified filter
        ->set('filterStatus', '')
        ->set('filterManagerVerified', '1')
        ->assertDontSee('Instansi Alpha')
        ->assertSee('Instansi Beta')
        // Test reset filters
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterSumberProyeksi', '')
        ->assertSet('filterStageId', '')
        ->assertSet('filterStatus', '')
        ->assertSet('filterManagerVerified', '');
});
