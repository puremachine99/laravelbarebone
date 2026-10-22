<?php

use App\Models\Province;
use App\Models\Regency;

beforeEach(function () {
    $this->withHeader('X-API-Key', publicApiToken());
});

test('provinsi keyword is required', function () {
    $response = $this->getJson('/api/regencies');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('provinsi');
});

test('returns regencies matching the given provinsi keyword', function () {
    $jatim = Province::factory()->create(['name' => 'Jawa Timur']);
    $jabar = Province::factory()->create(['name' => 'Jawa Barat']);

    $matching = Regency::factory()->create(['province_id' => $jatim->id, 'name' => 'Kabupaten Malang']);
    Regency::factory()->create(['province_id' => $jabar->id, 'name' => 'Kabupaten Bandung']);

    $response = $this->getJson('/api/regencies?provinsi=Jawa Timur');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('data.0.provinsi', 'Jawa Timur');
});

test('further narrows regencies with an optional name keyword', function () {
    $province = Province::factory()->create(['name' => 'Jawa Timur']);

    $malang = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Malang']);
    Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Sidoarjo']);

    $response = $this->getJson('/api/regencies?provinsi=Jawa Timur&keyword=Malang');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $malang->id);
});
