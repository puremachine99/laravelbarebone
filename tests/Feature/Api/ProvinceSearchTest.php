<?php

use App\Models\Province;

beforeEach(function () {
    $this->withHeader('X-API-Key', publicApiToken());
});

test('lists all provinces when no keyword is given', function () {
    Province::factory()->create(['name' => 'Jawa Timur']);
    Province::factory()->create(['name' => 'Jawa Barat']);

    $response = $this->getJson('/api/provinces');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('filters provinces by name keyword', function () {
    Province::factory()->create(['name' => 'Jawa Timur']);
    Province::factory()->create(['name' => 'Jawa Barat']);
    Province::factory()->create(['name' => 'Bali']);

    $response = $this->getJson('/api/provinces?keyword=Jawa');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('keyword filter is case-insensitive', function () {
    Province::factory()->create(['name' => 'Jawa Timur']);
    Province::factory()->create(['name' => 'Bali']);

    $response = $this->getJson('/api/provinces?keyword=JAWA TIMUR');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Jawa Timur');
});
