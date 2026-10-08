<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use App\Models\User;
use App\Support\ResourcePermissions;
use Database\Seeders\DataMasterInstansiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->region = Region::factory()->create(['name' => 'Wilayah Satu']);
    $province = Province::factory()->create(['name' => 'Prov. Aceh']);

    $this->regency = Regency::factory()->create([
        'province_id' => $province->id,
        'name' => 'Prov. Aceh',
        'type' => 'Provinsi',
        'region_id' => $this->region->id,
    ]);
});

it('empties pmo_opds and refills it from the CSV', function () {
    $stale = Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Instansi Tidak Ada Di CSV']);

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::find($stale->id)?->name)->not->toBe('Instansi Tidak Ada Di CSV')
        ->and(Opd::where('name', 'Instansi Tidak Ada Di CSV')->exists())->toBeFalse()
        ->and(Opd::where('regency_id', $this->regency->id)->where('name', 'Badan Kepegawaian Aceh')->value('jenis'))->toBe('OPD')
        ->and(Opd::whereNull('jenis')->count())->toBe(0);
});

it('attaches instansi without a region, such as Kementerian, to DKI Jakarta', function () {
    $jakarta = Regency::factory()->create([
        'province_id' => Province::factory()->create(['name' => 'Prov. DKI Jakarta'])->id,
        'name' => 'Prov. DKI Jakarta',
        'type' => 'Provinsi',
        'region_id' => $this->region->id,
    ]);

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::where('regency_id', $jakarta->id)->where('name', 'Kementerian Keuangan')->value('jenis'))->toBe('Kementrian');
});

it('restores contact and urusan links by regency and name', function () {
    $opd = Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Badan Kepegawaian Aceh']);
    $urusanId = DB::table('pmo_urusans')->insertGetId(['code' => 'U1', 'urusan' => 'Urusan Satu', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('pmo_opd_urusan')->insert(['opd_id' => $opd->id, 'urusan_id' => $urusanId]);
    $contactId = DB::table('pmo_contacts')->insertGetId([
        'name' => 'Budi', 'sumber' => 'SDS Umum', 'category' => 'Pemerintah Daerah', 'opd_id' => $opd->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(DataMasterInstansiSeeder::class);

    $newOpd = Opd::where('regency_id', $this->regency->id)->where('name', 'Badan Kepegawaian Aceh')->first();

    expect(DB::table('pmo_contacts')->where('id', $contactId)->value('opd_id'))->toBe($newOpd->id)
        ->and(DB::table('pmo_opd_urusan')->where('opd_id', $newOpd->id)->pluck('urusan_id')->all())->toBe([$urusanId]);
});

it('does not duplicate OPDs when the seeder runs twice', function () {
    $this->seed(DataMasterInstansiSeeder::class);
    $count = Opd::count();

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::count())->toBe($count);
});

it('filters OPDs by jenis and region', function () {
    $user = User::factory()->create();
    ResourcePermissions::ensureExist();
    $user->givePermissionTo(Permission::all());
    $this->actingAs($user);

    Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Dinas Alpha', 'jenis' => 'OPD']);
    Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Rumah Sakit Beta', 'jenis' => 'Unit Kesehatan']);

    Volt::test('master.opd.index')
        ->set('filterJenis', 'Unit Kesehatan')
        ->assertSee('Rumah Sakit Beta')
        ->assertDontSee('Dinas Alpha')
        ->set('filterJenis', '')
        ->set('filterRegion', $this->region->id)
        ->assertSee('Dinas Alpha')
        ->set('filterRegion', $this->region->id + 99)
        ->assertDontSee('Dinas Alpha');
});

it('matches CSV region spellings that differ from the database', function () {
    $province = Province::factory()->create(['name' => 'Prov. Daerah Istimewa Yogyakarta']);
    $diy = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Prov. DI Yogyakarta', 'type' => 'Provinsi', 'region_id' => $this->region->id]);
    $sultra = Regency::factory()->create([
        'province_id' => Province::factory()->create(['name' => 'Prov. Sulawesi Tenggara'])->id,
        'name' => 'Kota Kendari', 'type' => 'Kota', 'region_id' => $this->region->id,
    ]);

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::where('regency_id', $diy->id)->exists())->toBeTrue()
        ->and(Opd::where('regency_id', $sultra->id)->exists())->toBeTrue();
});
