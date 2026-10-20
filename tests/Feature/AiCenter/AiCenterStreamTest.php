<?php

use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\FakeAiGateway;
use Psr\Http\Message\StreamInterface;
use Spatie\Permission\Models\Permission;

function aiStreamUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    return $user;
}

function fakeAi(?AiErrorCode $failWith = null): FakeAiGateway
{
    $fake = new FakeAiGateway(failWith: $failWith);
    app()->instance(AiGateway::class, $fake);

    return $fake;
}

test('users without the AI Center permission cannot send messages', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Halo'])
        ->assertForbidden();
});

test('a first message creates a conversation, streams the answer and saves it', function () {
    $me = aiStreamUser();
    fakeAi();

    $response = $this->actingAs($me)->postJson(route('ai-center.messages.stream'), [
        'message' => 'Tolong ringkas notulen rapat berikut ini dengan singkat dan padat ya',
        'quick_action' => 'summarize',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->streamedContent();
    $conversation = AiConversation::sole();

    expect($content)
        ->toContain("event: conversation\ndata: {\"id\":{$conversation->id}}")
        ->toContain('event: delta')
        ->toContain('event: done');

    expect($conversation->user_id)->toBe($me->id)
        ->and($conversation->title)->toBe('Tolong ringkas notulen rapat berikut ini dengan singkat dan...')
        ->and($conversation->last_message_at)->not->toBeNull();

    [$question, $answer] = $conversation->messages()->oldest('id')->get();

    expect($question->role)->toBe(AiMessageRole::User)
        ->and($answer->role)->toBe(AiMessageRole::Assistant)
        ->and($answer->status)->toBe(AiMessageStatus::Complete)
        ->and($answer->content)->toContain('Mode uji');
});

test('follow-up messages send the earlier turns as history', function () {
    $me = aiStreamUser();
    $fake = fakeAi();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->for($conversation, 'conversation')->create(['content' => 'Pertanyaan pertama']);
    AiMessage::factory()->assistant()->for($conversation, 'conversation')->create(['content' => 'Jawaban pertama']);
    AiMessage::factory()->failed()->for($conversation, 'conversation')->create();

    $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $conversation->id, 'message' => 'Lanjut'])
        ->streamedContent();

    /** @var AiChatRequest $request */
    $request = $fake->requests[0];

    expect($request->conversationId)->toBe($conversation->id)
        ->and($request->message)->toBe('Lanjut')
        ->and($request->history)->toBe([
            ['role' => 'user', 'content' => 'Pertanyaan pertama'],
            ['role' => 'assistant', 'content' => 'Jawaban pertama'],
        ]);
});

test('history is limited to the configured window', function () {
    config(['ai_center.limits.history_window' => 2]);
    $me = aiStreamUser();
    $fake = fakeAi();
    $conversation = AiConversation::factory()->for($me)->create();
    foreach (['satu', 'dua', 'tiga'] as $content) {
        AiMessage::factory()->for($conversation, 'conversation')->create(['content' => $content]);
    }

    $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $conversation->id, 'message' => 'empat'])
        ->streamedContent();

    expect(array_column($fake->requests[0]->history, 'content'))->toBe(['dua', 'tiga']);
});

test('sending to someone else\'s conversation is not found', function () {
    $fake = fakeAi();
    $othersConversation = AiConversation::factory()->create();

    $this->actingAs(aiStreamUser())
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $othersConversation->id, 'message' => 'Halo'])
        ->assertNotFound();

    expect($fake->requests)->toBeEmpty();
});

test('a message over the length limit is refused without calling the AI', function () {
    config(['ai_center.limits.max_input_chars' => 10]);
    $fake = fakeAi();

    $content = $this->actingAs(aiStreamUser())
        ->postJson(route('ai-center.messages.stream'), ['message' => str_repeat('a', 11)])
        ->streamedContent();

    expect($content)->toContain('event: error')->toContain('input_too_long')
        ->and($fake->requests)->toBeEmpty()
        ->and(AiConversation::count())->toBe(0)
        ->and(AiMessage::count())->toBe(0);
});

test('reaching the daily limit refuses the message without calling the AI', function () {
    config(['ai_center.limits.daily_messages' => 2]);
    $me = aiStreamUser();
    $fake = fakeAi();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->count(2)->for($conversation, 'conversation')->create();

    $content = $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $conversation->id, 'message' => 'Satu lagi'])
        ->streamedContent();

    expect($content)->toContain('rate_limited')->toContain('Batas pemakaian harian')
        ->and($fake->requests)->toBeEmpty()
        ->and($conversation->messages()->count())->toBe(2);
});

test('yesterday\'s messages do not count toward today\'s limit', function () {
    config(['ai_center.limits.daily_messages' => 1]);
    $me = aiStreamUser();
    $fake = fakeAi();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->for($conversation, 'conversation')->create(['created_at' => now()->subDay()]);

    $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['conversation_id' => $conversation->id, 'message' => 'Halo'])
        ->streamedContent();

    expect($fake->requests)->toHaveCount(1);
});

test('an AI error is streamed and saved on the reply', function () {
    $me = aiStreamUser();
    fakeAi(failWith: AiErrorCode::UpstreamQuota);

    $content = $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Halo'])
        ->streamedContent();

    expect($content)->toContain('event: error')->toContain('upstream_quota');

    $answer = AiMessage::where('role', AiMessageRole::Assistant)->sole();
    expect($answer->status)->toBe(AiMessageStatus::Error)
        ->and($answer->error_code)->toBe(AiErrorCode::UpstreamQuota);
});

test('a crashing gateway is reported as a generic error without leaking details', function () {
    app()->instance(AiGateway::class, new class implements AiGateway
    {
        public function stream(AiChatRequest $request): iterable
        {
            yield from [];

            throw new RuntimeException('secret-key-in-message');
        }

        public function forget(string $remoteConversationId): void {}

        public function uploadFiles(array $files): array
        {
            return [];
        }

        public function generateDocument(array $document): array
        {
            return [];
        }

        public function downloadFile(string $remoteFileId): ?StreamInterface
        {
            return null;
        }

        public function deleteFile(string $remoteFileId): void {}
    });

    $content = $this->actingAs(aiStreamUser())
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Halo'])
        ->streamedContent();

    expect($content)->toContain('upstream_error')->not->toContain('secret-key-in-message');
    expect(AiMessage::where('role', AiMessageRole::Assistant)->sole()->status)->toBe(AiMessageStatus::Error);
});

test('an unknown quick action is rejected', function () {
    fakeAi();

    $this->actingAs(aiStreamUser())
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Halo', 'quick_action' => 'hack'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quick_action');
});
