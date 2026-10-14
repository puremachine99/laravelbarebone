<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;

/**
 * Region → Provinsi → Kabupaten/Kota → OPD, the id-based filters the cascading dropdowns use.
 * Regions are assigned per regency, so a province can appear under more than one region.
 */
beforeEach(function () {
    $this->regionOne = Region::factory()->create(['name' => 'Region 1', 'code' => 'REG-1']);
    $this->regionTwo = Region::factory()->create(['name' => 'Region 2', 'code' => 'REG-2']);

    $this->jabar = Province::factory()->create(['name' => 'Jawa Barat']);
    $this->jatim = Province::factory()->create(['name' => 'Jawa Timur']);
    $this->bali = Province::factory()->create(['name' => 'Bali']);

    $this->bandung = Regency::factory()->create(['province_id' => $this->jabar->id, 'region_id' => $this->regionOne->id, 'name' => 'Kab. Bandung']);
    $this->bandungBarat = Regency::factory()->create(['province_id' => $this->jabar->id, 'region_id' => $this->regionOne->id, 'name' => 'Kab. Bandung Barat']);
    $this->malang = Regency::factory()->create(['province_id' => $this->jatim->id, 'region_id' => $this->regionOne->id, 'name' => 'Kab. Malang']);
    $this->denpasar = Regency::factory()->create(['province_id' => $this->bali->id, 'region_id' => $this->regionTwo->id, 'name' => 'Kota Denpasar']);
});

test('lists regions with how many regencies they cover', function () {
    $this->getJson('/api/regions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', ['id' => $this->regionOne->id, 'name' => 'Region 1', 'code' => 'REG-1', 'regencies_count' => 3]);
});

test('provinces can be narrowed to a region', function () {
    $this->getJson('/api/provinces?region_id='.$this->regionOne->id)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Jawa Barat', 'Jawa Timur']);
});

test('regencies can be listed per region and narrowed to a province, without the provinsi keyword', function () {
    $this->getJson('/api/regencies?region_id='.$this->regionOne->id)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Kab. Bandung', 'Kab. Bandung Barat', 'Kab. Malang'])
        ->assertJsonPath('data.0.province_id', $this->jabar->id)
        ->assertJsonPath('data.0.region', 'Region 1');

    $this->getJson('/api/regencies?region_id='.$this->regionOne->id.'&province_id='.$this->jatim->id)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Kab. Malang']);
});

test('regencies still need some province or region filter', function () {
    $this->getJson('/api/regencies')->assertUnprocessable()->assertJsonValidationErrors('provinsi');
});

test('opds of exactly one regency, not of similarly named ones', function () {
    $mine = Opd::factory()->create(['regency_id' => $this->bandung->id, 'name' => 'Dinas Pendidikan']);
    Opd::factory()->create(['regency_id' => $this->bandungBarat->id, 'name' => 'Dinas Pendidikan']);

    $this->getJson('/api/opds?regency_id='.$this->bandung->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.regency', 'Kab. Bandung');
});
