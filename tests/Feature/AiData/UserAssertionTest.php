<?php

use App\Models\User;
use App\Services\AiData\UserAssertion;

beforeEach(function () {
    config(['ai_center.data_api.assertion_secret' => 'test-secret', 'ai_center.data_api.assertion_ttl_seconds' => 300]);
    $this->assertions = app(UserAssertion::class);
    $this->user = User::factory()->create();
});

test('an issued assertion verifies back to its user and conversation', function () {
    $assertion = $this->assertions->issue($this->user, 345);

    expect($assertion)->toMatch('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/')
        ->and($this->assertions->verify($assertion))->toBe(['user_id' => $this->user->id, 'conversation_id' => 345]);
});

test('the payload is readable but changing it breaks the signature', function () {
    [$payload, $signature] = explode('.', $this->assertions->issue($this->user, 345));

    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    expect($claims['uid'])->toBe($this->user->id);

    $forged = rtrim(strtr(base64_encode(json_encode([...$claims, 'uid' => $this->user->id + 1])), '+/', '-_'), '=');

    expect($this->assertions->verify("{$forged}.{$signature}"))->toBeNull();
});

test('an assertion signed with another secret is rejected', function () {
    $assertion = $this->assertions->issue($this->user, 345);

    config(['ai_center.data_api.assertion_secret' => 'another-secret']);

    expect($this->assertions->verify($assertion))->toBeNull();
});

test('an expired assertion is rejected', function () {
    $assertion = $this->assertions->issue($this->user, 345);

    $this->travel(301)->seconds();

    expect($this->assertions->verify($assertion))->toBeNull();
});

test('malformed assertions are rejected', function (string $assertion) {
    expect($this->assertions->verify($assertion))->toBeNull();
})->with(['', 'abc', 'a.b.c', '.', 'eyJ1aWQiOiJ4In0.sig']);

test('without a secret nothing is issued and nothing verifies', function () {
    $assertion = $this->assertions->issue($this->user, 345);

    config(['ai_center.data_api.assertion_secret' => null]);

    expect($this->assertions->issue($this->user, 345))->toBeNull()
        ->and($this->assertions->verify($assertion))->toBeNull();
});
