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

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
});

test('guests are redirected to login from realisasi', function () {
    $this->get(route('partnership.realisasi'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without access-partnership permission cannot access realisasi page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('partnership.realisasi'))
        ->assertStatus(403);
});

test('authorized users with access-partnership permission can access realisasi page', function () {
    $user = User::factory()->create();
    $permission = moduleAccess('access-partnership');
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->get(route('partnership.realisasi'))
        ->assertStatus(200)
        ->assertSeeLivewire('partnership.realisasi-index');
});

test('realisasi page renders completed cards only', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    $this->actingAs($user);

    (new PipelineSeeder)->run();

    $region = Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $province = Province::create(['name' => 'Jawa Timur', 'region_id' => $region->id]);
    $regency = Regency::create(['name' => 'Kabupaten Malang', 'province_id' => $province->id]);
    $opd = Opd::create(['name' => 'Dinas Kesehatan', 'regency_id' => $regency->id]);

    $contact = Contact::create([
        'name' => 'PIC Budi',
        'sumber' => 'SDS Umum',
        'region_id' => $region->id,
        'category' => 'Pemerintah Daerah',
        'regency_id' => $regency->id,
        'opd_id' => $opd->id,
    ]);

    $div = Division::create(['code' => 'DIV-1', 'name' => 'Division 1']);
    $sc = ServiceCenter::create(['division_id' => $div->id, 'code' => 'SC-1', 'full_code' => 'SC-FC-1', 'name' => 'Service Center 1']);
    $cat = ProductCategory::create(['code' => 'CAT-1', 'name' => 'Category 1']);
    $product = Output::create([
        'division_id' => $div->id,
        'service_center_id' => $sc->id,
        'category_code' => $cat->code,
        'output_name' => 'Aplikasi SmartID v3',
        'pelaksana' => 'SmartID',
        'status' => true,
        'sequence' => 1,
        'generated_code' => 'OUT-1',
    ]);

    $stageTerlaksana = PipelineStage::where('stage_name', 'Terlaksana')->first();

    // 1. Positive Card (should be visible)
    $positiveCard = Card::create([
        'title' => 'Aplikasi SmartID v3',
        'stage_id' => $stageTerlaksana->id,
        'contact_id' => $contact->id,
        'nominal_value' => 50000000,
        'status' => 'won',
        'sumber_proyeksi' => 'SIRUP',
        'pitching_date' => date('Y-m-d'),
        'projection_month' => date('Y-m'),
        'projection_week' => 'Pekan 2',
        'projection_status' => 'Optimis',
        'drive_link' => 'https://drive.google.com/test',
        'manager_verified' => true,
    ]);

    // 2. Negative Card (should not be visible)
    $negativeCard = Card::create([
        'title' => 'Aplikasi SmartID v3',
        'stage_id' => $stageTerlaksana->id,
        'contact_id' => $contact->id,
        'nominal_value' => 30000000,
        'status' => 'lost',
        'sumber_proyeksi' => 'SIRUP',
        'pitching_date' => date('Y-m-d'),
        'negative_reason' => 'No Budget',
    ]);

    Livewire::test('partnership.realisasi-index')
        ->assertSet('filterFiscalYear', (string) date('Y'))
        ->assertSee('PIC Budi')
        ->assertDontSee('Rp 30.000.000');
});

test('realisasi page filter and search functionality works', function () {
    (new PipelineSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo(['access-partnership']);

    $region = Region::first() ?: Region::create(['name' => 'Region Jawa', 'code' => 'REG-JAWA']);
    $contact1 = Contact::create(['name' => 'Instansi Alpha', 'sumber' => 'SIRUP', 'region_id' => $region->id, 'category' => 'Pemerintah Daerah']);
    $contact2 = Contact::create(['name' => 'Instansi Beta', 'sumber' => 'SIPD', 'region_id' => $region->id, 'category' => 'Pemerintah Daerah']);

    $pitchingProfile = PipelineProfile::where('pipeline_name', 'Pitching')->first();
    $stageTerlaksana = $pitchingProfile->stages()->where('stage_name', 'Terlaksana')->first();

    $card1 = Card::create([
        'title' => 'Product Alpha',
        'stage_id' => $stageTerlaksana->id,
        'contact_id' => $contact1->id,
        'nominal_value' => 10000000,
        'status' => 'won',
        'sumber_proyeksi' => 'SIRUP',
        'manager_verified' => false,
    ]);

    $card2 = Card::create([
        'title' => 'Product Beta',
        'stage_id' => $stageTerlaksana->id,
        'contact_id' => $contact2->id,
        'nominal_value' => 20000000,
        'status' => 'hot',
        'sumber_proyeksi' => 'SIPD',
        'manager_verified' => true,
    ]);

    // Test search filter
    Livewire::actingAs($user)
        ->test('partnership.realisasi-index')
        ->set('search', 'Alpha')
        ->assertSee('Instansi Alpha')
        ->assertDontSee('Instansi Beta')
        // Test sumber filter
        ->set('search', '')
        ->set('filterSumberProyeksi', 'SIRUP')
        ->assertSee('Instansi Alpha')
        ->assertDontSee('Instansi Beta')
        // Test status filter
        ->set('filterSumberProyeksi', '')
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
        ->assertSet('filterStatus', '')
        ->assertSet('filterManagerVerified', '');
});
