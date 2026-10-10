<?php

test('the public lookup API allows normal use and limits a burst per IP', function () {
    foreach (range(1, 120) as $request) {
        $this->getJson('/api/provinces')->assertOk();
    }

    $this->getJson('/api/provinces')->assertTooManyRequests();

    // The limit is shared by every public endpoint and counted per IP, so another client is unaffected.
    $this->getJson('/api/opds/search')->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('/api/provinces')->assertOk();
});

test('responses report the remaining allowance', function () {
    $this->getJson('/api/provinces')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 120)
        ->assertHeader('X-RateLimit-Remaining', 119);
});
