<?php

use App\Enums\AiErrorCode;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\NakamaAiGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    config([
        'ai_center.driver' => 'nakama',
        'ai_center.nakama.url' => 'https://nakama-ai.test',
        'ai_center.nakama.api_key' => 'nk-super-secret',
    ]);
});

function nakamaRequest(?string $remoteId = null): AiChatRequest
{
    return new AiChatRequest(
        requestId: 'req-1',
        userId: 7,
        userName: 'Budi Santoso',
        conversationId: 3,
        message: 'Apa itu Renstra?',
        history: [['role' => 'user', 'content' => 'riwayat lokal']],
        remoteConversationId: $remoteId,
    );
}

function sse(array $events): string
{
    return collect($events)
        ->map(fn (array $event) => "event: {$event[0]}\ndata: ".json_encode($event[1])."\n\n")
        ->prepend(": keep-alive\n\n")
        ->implode('');
}

/**
 * @return array<int, array{type: string, text: string, code: ?string}>
 */
function collectNakama(AiChatRequest $request): array
{
    $events = [];

    foreach ((new NakamaAiGateway)->stream($request) as $event) {
        $events[] = ['type' => $event->type, 'text' => $event->text, 'code' => $event->errorCode?->value, 'event' => $event];
    }

    return $events;
}

test('it streams text deltas and reports usage and the remote thread id', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([
        ['start', ['conversation_id' => 'conv_9b17', 'model' => 'gemini-3.7-flash']],
        ['text_delta', ['text' => 'Renstra adalah ']],
        ['title', ['title' => 'Renstra']],
        ['text_delta', ['text' => 'rencana strategis.']],
        ['done', ['stop_reason' => 'end_turn', 'message' => ['model' => 'gemini-3.7-flash', 'tokens' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15]]]],
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    $events = collectNakama(nakamaRequest());

    expect(array_column($events, 'type'))->toBe(['started', 'delta', 'delta', 'done'])
        ->and($events[0]['event']->remoteConversationId)->toBe('conv_9b17')
        ->and($events[1]['text'].$events[2]['text'])->toBe('Renstra adalah rencana strategis.')
        ->and($events[3]['event']->usage)->toBe(['prompt_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15])
        ->and($events[3]['event']->finishReason)->toBe('end_turn');
});

test('it sends the key as a header and nothing sensitive in the body, with every tool off', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([['done', ['message' => ['tokens' => []]]]]))]);

    collectNakama(nakamaRequest('conv_9b17'));

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://nakama-ai.test/v1/chat'
            && $request->hasHeader('X-API-Key', 'nk-super-secret')
            && $body['conversation_id'] === 'conv_9b17'
            && $body['message'] === 'Apa itu Renstra?'
            && $body['user_id'] === 'smartid-7'
            && $body['session_id'] === 'ai-center-3'
            && $body['stream'] === true
            && ! in_array(true, $body['tools'], true)
            && ! str_contains($request->body(), 'Budi')
            && ! str_contains($request->body(), 'riwayat lokal')
            && ! str_contains($request->body(), 'nk-super-secret');
    });
});

test('http and in-stream errors are mapped to the standard error codes', function (int $status, AiErrorCode $expected) {
    Http::fake(['*/v1/chat' => Http::response(['error' => ['code' => 'x', 'message' => 'detail']], $status)]);

    $events = collectNakama(nakamaRequest());

    expect($events)->toHaveCount(1)
        ->and($events[0]['code'])->toBe($expected->value);
})->with([
    [429, AiErrorCode::UpstreamQuota],
    [400, AiErrorCode::UpstreamBlocked],
    [401, AiErrorCode::UpstreamError],
    [409, AiErrorCode::UpstreamError],
    [503, AiErrorCode::UpstreamError],
]);

test('an error event inside the stream ends it with the mapped code', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([['error', ['code' => 'rate_limited', 'message' => 'x', 'status' => 429]]]))]);

    expect(collectNakama(nakamaRequest())[0]['code'])->toBe('upstream_quota');
});

test('a stream cut before the done event is an error', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([['text_delta', ['text' => 'Hal']]]))]);

    $events = collectNakama(nakamaRequest());

    expect(array_column($events, 'type'))->toBe(['delta', 'error']);
});

test('a connection failure is a timeout error and never exposes the key', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for nk-super-secret'));

    $events = collectNakama(nakamaRequest());

    expect($events)->toHaveCount(1)
        ->and($events[0]['code'])->toBe('upstream_timeout')
        ->and(json_encode($events[0]['event']->toStreamedEvent()->data))->not->toContain('nk-super-secret');
});

test('abandoning the stream early cancels the remote answer', function () {
    Http::fake([
        '*/v1/chat/conv_9b17/cancel' => Http::response(['cancelled' => true]),
        '*/v1/chat' => Http::response(sse([
            ['start', ['conversation_id' => 'conv_9b17']],
            ['text_delta', ['text' => 'Halo']],
        ])),
    ]);

    $generator = (new NakamaAiGateway)->stream(nakamaRequest());
    $generator->current(); // started
    $generator->next();    // first delta
    unset($generator);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/chat/conv_9b17/cancel'));
});

test('forgetting a conversation deletes it remotely and swallows failures', function () {
    Http::fake(['*/v1/conversations/conv_9b17' => Http::response(status: 500)]);

    (new NakamaAiGateway)->forget('conv_9b17');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://nakama-ai.test/v1/conversations/conv_9b17');
});

test('the chat endpoint saves the remote thread id and reuses it on the next message', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([
        ['start', ['conversation_id' => 'conv_9b17']],
        ['text_delta', ['text' => 'Halo!']],
        ['done', ['message' => ['tokens' => ['total_tokens' => 3]]]],
    ]))]);

    $me = User::factory()->create();
    $me->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    $content = $this->actingAs($me)->postJson(route('ai-center.messages.stream'), ['message' => 'Halo'])->streamedContent();
    $conversation = AiConversation::sole();

    expect($content)->toContain('event: delta')->toContain('event: done')->not->toContain('conv_9b17')
        ->and($conversation->remote_conversation_id)->toBe('conv_9b17');

    $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $conversation->id, 'message' => 'Lagi'])
        ->streamedContent();

    Http::assertSent(fn (Request $request) => ($request->data()['conversation_id'] ?? null) === 'conv_9b17');
});

test('the user context is sent only when present', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([['done', ['message' => ['tokens' => []]]]]))]);
    $context = ['department' => 'Departemen Finance', 'roles' => ['Investor Admin'], 'available_tools' => ['fo_achievement'], 'assertion' => 'payload.signature'];

    collectNakama(nakamaRequest());
    collectNakama(nakamaRequest()->withUserContext($context, 'prompt mode data'));

    $bodies = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->data());

    expect($bodies[0])->not->toHaveKey('user_context')
        ->and($bodies[1]['user_context'])->toBe($context)
        ->and($bodies[1]['system_prompt'])->toBe('prompt mode data');
});

test('thinking and tool calls become progress statuses, repeated ones collapsed', function () {
    Http::fake(['*/v1/chat' => Http::response(sse([
        ['start', ['conversation_id' => 'conv_9b17']],
        ['thinking_delta', ['text' => 'a']],
        ['thinking_delta', ['text' => 'b']],
        ['tool_call', ['name' => 'nakama_data', 'input' => ['dataset' => 'employees']]],
        ['thinking_delta', ['text' => 'c']],
        ['tool_call', ['name' => 'create_document', 'input' => ['sheets' => [[]]]]],
        ['progress', ['elapsed_seconds' => 5]],
        ['text_delta', ['text' => 'Selesai.']],
        ['done', ['message' => ['tokens' => []]]],
    ]))]);

    $events = collect(collectNakama(nakamaRequest()));

    expect($events->where('type', 'status')->map(fn ($e) => $e['event']->phase)->values()->all())
        ->toBe(['thinking', 'data', 'thinking', 'document'])
        ->and($events->where('type', 'status')->last()['text'])->toBe('Menyusun file Excel…')
        ->and($events->last()['type'])->toBe('done');
});
