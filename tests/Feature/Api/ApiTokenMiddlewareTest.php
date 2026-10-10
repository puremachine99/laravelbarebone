<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/api/_token-test', fn () => response()->json(['ok' => true]))->middleware('api.token');

    config(['ai_center.data_api.token' => 'nkm_correct-token']);
});

test('accepts the configured bearer token', function () {
    $this->withToken('nkm_correct-token')->getJson('/api/_token-test')->assertOk();
});

test('rejects a request without a token', function () {
    $this->getJson('/api/_token-test')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer');
});

test('rejects a wrong token', function () {
    $this->withToken('nkm_wrong-token')->getJson('/api/_token-test')->assertUnauthorized();
});

test('rejects Basic Auth credentials', function () {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:nkm_correct-token')])
        ->getJson('/api/_token-test')
        ->assertUnauthorized();
});

test('rejects everything while no token is configured', function () {
    config(['ai_center.data_api.token' => null]);

    $this->getJson('/api/_token-test')->assertUnauthorized();
    $this->withToken('')->getJson('/api/_token-test')->assertUnauthorized();
    $this->withToken('anything')->getJson('/api/_token-test')->assertUnauthorized();
});
