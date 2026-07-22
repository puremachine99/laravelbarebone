<?php

use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;

test('provinsi keyword is required', function () {
    $response = $this->getJson('/api/opds');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('provinsi');
});

test('returns opds matching the given provinsi keyword', function () {
    $jatim = Province::factory()->create(['name' => 'Jawa Timur']);
    $jabar = Province::factory()->create(['name' => 'Jawa Barat']);

    $regencyJatim = Regency::factory()->create(['province_id' => $jatim->id]);
    $regencyJabar = Regency::factory()->create(['province_id' => $jabar->id]);

    $matching = Opd::factory()->create(['regency_id' => $regencyJatim->id, 'name' => 'Dinas Pendidikan']);
    Opd::factory()->create(['regency_id' => $regencyJabar->id, 'name' => 'Dinas Kesehatan']);

    $response = $this->getJson('/api/opds?provinsi=Jawa Timur');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('data.0.provinsi', 'Jawa Timur');
});

test('matches provinsi keyword partially and case-insensitively', function () {
    $province = Province::factory()->create(['name' => 'Jawa Timur']);
    $regency = Regency::factory()->create(['province_id' => $province->id]);
    Opd::factory()->create(['regency_id' => $regency->id]);

    $response = $this->getJson('/api/opds?provinsi=timur');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('further narrows results with optional kabupaten and keyword filters', function () {
    $province = Province::factory()->create(['name' => 'Jawa Timur']);
    $malang = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Malang']);
    $sidoarjo = Regency::factory()->create(['province_id' => $province->id, 'name' => 'Kabupaten Sidoarjo']);

    $matching = Opd::factory()->create(['regency_id' => $malang->id, 'name' => 'Dinas Pendidikan']);
    Opd::factory()->create(['regency_id' => $malang->id, 'name' => 'Dinas Kesehatan']);
    Opd::factory()->create(['regency_id' => $sidoarjo->id, 'name' => 'Dinas Pendidikan']);

    $response = $this->getJson('/api/opds?provinsi=Jawa Timur&kabupaten=Malang&keyword=Pendidikan');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});
