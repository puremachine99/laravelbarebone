<?php

use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\NakamaAiGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('ai:ping succeeds with the fake driver', function () {
    config(['ai_center.driver' => 'fake']);

    $this->artisan('ai:ping')->assertSuccessful();
});

test('ai:ping reports an unreachable nakama host', function () {
    config(['ai_center.driver' => 'nakama', 'ai_center.nakama.url' => 'https://nakama.test']);

    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $this->artisan('ai:ping')->assertFailed();
});

test('ai:ping reports a gateway error from nakama', function () {
    config(['ai_center.driver' => 'nakama', 'ai_center.nakama.url' => 'https://nakama.test']);
    app()->bind(AiGateway::class, NakamaAiGateway::class);

    Http::fake(['nakama.test/v1/chat' => Http::response('', 504), 'nakama.test' => Http::response('ok')]);

    $this->artisan('ai:ping')->assertFailed();
});
