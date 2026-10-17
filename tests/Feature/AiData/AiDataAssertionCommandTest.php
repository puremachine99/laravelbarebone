<?php

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AiData\UserAssertion;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['ai_center.data_api.assertion_secret' => 'test-secret', 'ai_center.data_api.token' => 'test-token']);
});

test('issues an assertion the data API accepts', function () {
    $user = User::factory()->create(['email' => 'tester@example.com']);
    $conversation = AiConversation::factory()->for($user)->create();

    Artisan::call('ai-data:assertion', ['user' => 'tester@example.com']);
    $assertion = trim(strtok(Artisan::output(), "\n"));

    expect(app(UserAssertion::class)->verify($assertion))
        ->toBe(['user_id' => $user->id, 'conversation_id' => $conversation->id]);

    $this->withHeaders(['Authorization' => 'Bearer test-token', 'X-AI-User-Assertion' => $assertion])
        ->getJson(route('api.ai.datasets'))
        ->assertOk();
});

test('caps the lifetime at 60 minutes', function () {
    $user = User::factory()->create(['email' => 'tester@example.com']);
    AiConversation::factory()->for($user)->create();

    Artisan::call('ai-data:assertion', ['user' => 'tester@example.com', '--minutes' => 500]);
    $assertion = trim(strtok(Artisan::output(), "\n"));

    $this->travel(59)->minutes();
    expect(app(UserAssertion::class)->verify($assertion))->not->toBeNull();

    $this->travel(2)->minutes();
    expect(app(UserAssertion::class)->verify($assertion))->toBeNull();
});

test('fails for a user without an AI Center conversation', function () {
    User::factory()->create(['email' => 'tester@example.com']);

    $this->artisan('ai-data:assertion', ['user' => 'tester@example.com'])->assertFailed();
});

test('fails for an unknown user', function () {
    $this->artisan('ai-data:assertion', ['user' => 'nobody@example.com'])->assertFailed();
});

test('fails when the assertion secret is not configured', function () {
    config(['ai_center.data_api.assertion_secret' => '']);

    $this->artisan('ai-data:assertion', ['user' => 'tester@example.com'])->assertFailed();
});
