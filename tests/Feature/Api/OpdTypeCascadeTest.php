<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;

/**
 * Jenis → (Region → Provinsi → Kabupaten/Kota) → OPD. Kementrian has no location, so it skips straight to the list;
 * every other jenis narrows the location dropdowns to places that have an OPD of that jenis.
 */
beforeEach(function () {
    $this->withHeader('X-API-Key', publicApiToken());
    $this->regionOne = Region::factory()->create(['name' => 'Region 1']);
    $this->regionTwo = Region::factory()->create(['name' => 'Region 2']);

    $this->dki = Province::factory()->create(['name' => 'Prov. DKI Jakarta']);
    $this->jatim = Province::factory()->create(['name' => 'Jawa Timur']);

    $this->dkiPlaceholder = Regency::factory()->create(['province_id' => $this->dki->id, 'region_id' => $this->regionOne->id, 'name' => 'Prov. DKI Jakarta']);
    $this->malang = Regency::factory()->create(['province_id' => $this->jatim->id, 'region_id' => $this->regionOne->id, 'name' => 'Kab. Malang']);
    $this->surabaya = Regency::factory()->create(['province_id' => $this->jatim->id, 'region_id' => $this->regionTwo->id, 'name' => 'Kota Surabaya']);

    $this->kemenhub = Opd::factory()->create(['regency_id' => $this->dkiPlaceholder->id, 'name' => 'Kementerian Perhubungan', 'jenis' => 'Kementrian']);
    $this->kemendagri = Opd::factory()->create(['regency_id' => $this->dkiPlaceholder->id, 'name' => 'Kementerian Dalam Negeri', 'jenis' => 'Kementrian']);
    $this->rsudMalang = Opd::factory()->create(['regency_id' => $this->malang->id, 'name' => 'RSUD Kanjuruhan', 'jenis' => 'Unit Kesehatan']);
    $this->dinasMalang = Opd::factory()->create(['regency_id' => $this->malang->id, 'name' => 'Dinas Pendidikan', 'jenis' => 'OPD']);
    $this->dinasSurabaya = Opd::factory()->create(['regency_id' => $this->surabaya->id, 'name' => 'Dinas Kesehatan', 'jenis' => 'OPD']);
});

test('lists every jenis with its count and whether it has a location', function () {
    $this->getJson('/api/opd-types')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['name' => 'Kementrian', 'has_location' => false, 'opds_count' => 2],
            ['name' => 'OPD', 'has_location' => true, 'opds_count' => 2],
            ['name' => 'Unit Kesehatan', 'has_location' => true, 'opds_count' => 1],
        ]]);
});

test('kementrian is listed directly, without provinsi or regency, and exposes no location', function () {
    $this->getJson('/api/opds?jenis=Kementrian')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Kementerian Dalam Negeri', 'Kementerian Perhubungan'])
        ->assertJsonPath('data.0.regency', null)
        ->assertJsonPath('data.0.provinsi', null);
});

test('jenis matches case-insensitively', function () {
    $this->getJson('/api/opds?jenis=kementrian')->assertOk()->assertJsonCount(2, 'data');
});

test('located jenis still need a provinsi or regency', function () {
    $this->getJson('/api/opds?jenis=OPD')->assertUnprocessable()->assertJsonValidationErrors('provinsi');
});

test('opds of one regency can be narrowed to a jenis', function () {
    $this->getJson('/api/opds?regency_id='.$this->malang->id.'&jenis=Unit Kesehatan')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->rsudMalang->id]);
});

test('kementrian does not leak into location-based OPD lists', function () {
    $this->getJson('/api/opds?provinsi=Jakarta')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/opds?regency_id='.$this->dkiPlaceholder->id)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/opds/search?provinsi=Jakarta')->assertOk()->assertJsonCount(0, 'data');
});

test('regions, provinces and regencies follow the chosen jenis', function () {
    $this->getJson('/api/regions?jenis=Unit Kesehatan')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Region 1'])
        ->assertJsonPath('data.0.regencies_count', 1);

    $this->getJson('/api/provinces?jenis=Unit Kesehatan')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Jawa Timur']);

    $this->getJson('/api/provinces?region_id='.$this->regionTwo->id.'&jenis=Unit Kesehatan')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/regencies?province_id='.$this->jatim->id.'&jenis=OPD')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Kab. Malang', 'Kota Surabaya']);

    $this->getJson('/api/regencies?region_id='.$this->regionOne->id.'&jenis=Unit Kesehatan')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Kab. Malang']);
});

test('kementrian has no regions, provinces or regencies', function () {
    $this->getJson('/api/regions?jenis=Kementrian')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/provinces?jenis=Kementrian')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/regencies?region_id='.$this->regionOne->id.'&jenis=Kementrian')->assertOk()->assertJsonCount(0, 'data');
});

describe('province level', function () {
    beforeEach(function () {
        $this->jatimLevel = Regency::factory()->create(['province_id' => $this->jatim->id, 'region_id' => $this->regionOne->id, 'name' => 'Prov. Jawa Timur', 'type' => Regency::TYPE_PROVINCE]);
        $this->bappedaJatim = Opd::factory()->create(['regency_id' => $this->jatimLevel->id, 'name' => 'Bappeda Provinsi', 'jenis' => 'OPD']);
        $this->rsudJatim = Opd::factory()->create(['regency_id' => $this->jatimLevel->id, 'name' => 'RSUD dr. Soetomo', 'jenis' => 'Unit Kesehatan']);
    });

    test('a province alone lists only its province-level OPDs', function () {
        $this->getJson('/api/opds?province_id='.$this->jatim->id.'&jenis=OPD')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->bappedaJatim->id])
            ->assertJsonPath('data.0.regency', 'Prov. Jawa Timur');

        $this->getJson('/api/opds?province_id='.$this->jatim->id)
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Bappeda Provinsi', 'RSUD dr. Soetomo']);
    });

    test('adding a regency narrows to that kabupaten/kota', function () {
        $this->getJson('/api/opds?province_id='.$this->jatim->id.'&regency_id='.$this->malang->id.'&jenis=OPD')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->dinasMalang->id]);
    });

    test('a regency of another province gives nothing', function () {
        $this->getJson('/api/opds?province_id='.$this->dki->id.'&regency_id='.$this->malang->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('kementrian placeholder never counts as a province-level OPD', function () {
        $this->dkiPlaceholder->update(['type' => Regency::TYPE_PROVINCE]);

        $this->getJson('/api/opds?province_id='.$this->dki->id)->assertOk()->assertJsonCount(0, 'data');
    });

    test('regencies can drop the province-level entry once a province is picked', function () {
        $this->getJson('/api/regencies?province_id='.$this->jatim->id)
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Kab. Malang', 'Kota Surabaya', 'Prov. Jawa Timur']);

        $this->getJson('/api/regencies?province_id='.$this->jatim->id.'&opd_lv=1')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Kab. Malang', 'Kota Surabaya']);
    });
});
