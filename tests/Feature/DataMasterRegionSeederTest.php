<?php

use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Database\Seeders\DataMasterRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->region2 = Region::factory()->create(['code' => 'REG-2']);
    $this->region4 = Region::factory()->create(['code' => 'REG-4']);

    $this->aceh = Province::factory()->create(['name' => 'Prov. Aceh']);
    $this->bali = Province::factory()->create(['name' => 'Prov. Bali']);

    $this->acehProvinceRegency = Regency::factory()->create([
        'province_id' => $this->aceh->id,
        'name' => 'Prov. Aceh',
        'region_id' => null,
    ]);
    $this->acehBaratRegency = Regency::factory()->create([
        'province_id' => $this->aceh->id,
        'name' => 'Kab. Aceh Barat',
        'region_id' => null,
    ]);
    $this->badungRegency = Regency::factory()->create([
        'province_id' => $this->bali->id,
        'name' => 'Kab. Badung',
        'region_id' => $this->region2->id,
    ]);
});

it('sets region_id on provinces and regencies from the CSV', function () {
    $this->seed(DataMasterRegionSeeder::class);

    expect($this->acehProvinceRegency->fresh()->region_id)->toBe($this->region2->id)
        ->and($this->acehBaratRegency->fresh()->region_id)->toBe($this->region2->id)
        ->and($this->badungRegency->fresh()->region_id)->toBe($this->region4->id);
});

it('can be run again without changing data', function () {
    $this->seed(DataMasterRegionSeeder::class);
    $this->seed(DataMasterRegionSeeder::class);

    expect($this->acehBaratRegency->fresh()->region_id)->toBe($this->region2->id)
        ->and(Regency::query()->count())->toBe(3);
});

it('overrides an existing region assignment', function () {
    $this->acehBaratRegency->update(['region_id' => $this->region4->id]);

    $this->seed(DataMasterRegionSeeder::class);

    expect($this->acehBaratRegency->fresh()->region_id)->toBe($this->region2->id);
});

it('renames provinces to the spelling used in the CSV', function () {
    $region3 = Region::factory()->create(['code' => 'REG-3']);
    $province = Province::factory()->create(['name' => 'Prov. Daerah Khusus Jakarta']);
    $jakarta = Regency::factory()->create([
        'province_id' => $province->id, 'name' => 'Prov. DKI Jakarta', 'type' => 'Provinsi', 'region_id' => null,
    ]);

    $this->seed(DataMasterRegionSeeder::class);

    expect($jakarta->fresh()->region_id)->toBe($region3->id)
        ->and($province->fresh()->name)->toBe('Prov. DKI Jakarta');
});
