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

it('searches across every column of the OPD table', function () {
    $user = User::factory()->create();
    ResourcePermissions::ensureExist();
    $user->givePermissionTo(Permission::all());
    $this->actingAs($user);

    $urusanId = DB::table('pmo_urusans')->insertGetId(['code' => 'U9', 'urusan' => 'Pendidikan Dasar', 'created_at' => now(), 'updated_at' => now()]);
    $alpha = Opd::factory()->create(['regency_id' => $this->regency->id, 'name' => 'Dinas Alpha', 'jenis' => 'OPD']);
    DB::table('pmo_opd_urusan')->insert(['opd_id' => $alpha->id, 'urusan_id' => $urusanId]);
    $otherRegency = Regency::factory()->create([
        'province_id' => Province::factory()->create(['name' => 'Prov. Lain'])->id,
        'name' => 'Kab. Lain', 'type' => 'Kabupaten', 'region_id' => null,
    ]);
    Opd::factory()->create(['regency_id' => $otherRegency->id, 'name' => 'Kementerian Beta', 'jenis' => 'Kementrian']);

    foreach (['alpha', 'opd', 'prov. aceh', 'wilayah satu', 'pendidikan dasar', 'U9', 'alpha aceh'] as $keyword) {
        Volt::test('master.opd.index')->set('search', $keyword)->assertSee('Dinas Alpha')->assertDontSee('Kementerian Beta');
    }

    Volt::test('master.opd.index')->set('search', 'kab. lain')->assertSee('Kementerian Beta')->assertDontSee('Dinas Alpha');
    Volt::test('master.opd.index')->set('search', 'alpha lain')->assertDontSee('Dinas Alpha')->assertDontSee('Kementerian Beta');
});

it('attaches Kementerian to the numbered DKI Jakarta province regency used on dev', function () {
    $jakarta = Regency::factory()->create([
        'province_id' => Province::factory()->create(['name' => 'Prov. DKI Jakarta'])->id,
        'name' => 'Prov. Daerah Khusus Jakarta 1',
        'type' => 'Provinsi',
        'region_id' => $this->region->id,
    ]);

    $this->seed(DataMasterInstansiSeeder::class);

    expect(Opd::where('regency_id', $jakarta->id)->where('name', 'Kementerian Keuangan')->exists())->toBeTrue();
});

it('shows a summary of instansi per jenis, coverage and region that follows the filters', function () {
    $user = User::factory()->create();
    ResourcePermissions::ensureExist();
    $user->givePermissionTo(Permission::all());
    $this->actingAs($user);

    Opd::factory()->count(3)->create(['regency_id' => $this->regency->id, 'jenis' => 'OPD']);
    Opd::factory()->count(2)->create(['regency_id' => $this->regency->id, 'jenis' => 'Kementrian']);
    Opd::factory()->create(['regency_id' => $this->regency->id, 'jenis' => 'Unit Kesehatan']);

    $component = Volt::test('master.opd.index');

    expect($component->get('summary'))->toMatchArray(['total' => 6, 'regencies' => 1, 'provinces' => 1, 'withUrusan' => 0])
        ->and($component->get('summary')['jenis']->all())->toBe(['OPD' => 3, 'Kementrian' => 2, 'Unit Kesehatan' => 1])
        ->and($component->get('summary')['perRegion']->all())->toBe(['Wilayah Satu' => 6]);

    $component->set('filterJenis', 'Kementrian')->assertSee('TOTAL INSTANSI & JENIS', false);

    expect($component->get('summary')['total'])->toBe(2);
});
