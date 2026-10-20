<?php

use App\Enums\AiErrorCode;
use App\Livewire\Ai\AiCenter;
use App\Models\AiConversation;
use App\Models\AiFile;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiFileUploadException;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\AiStreamEvent;
use App\Services\AiCenter\FakeAiGateway;
use App\Services\AiCenter\NakamaAiGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function aiFilesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    return $user;
}

function fakeFilesAi(?AiErrorCode $failWith = null): FakeAiGateway
{
    $fake = new FakeAiGateway(failWith: $failWith);
    app()->instance(AiGateway::class, $fake);

    return $fake;
}

test('users without the AI Center permission cannot upload files', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10)]])
        ->assertForbidden();
});

test('an upload is sent to the AI service and remembered as a pending file of the user', function () {
    $me = aiFilesUser();
    $fake = fakeFilesAi();

    $response = $this->actingAs($me)
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')]])
        ->assertCreated()
        ->assertJsonPath('files.0.name', 'laporan.pdf');

    $file = AiFile::sole();

    expect($fake->uploaded)->toBe(['laporan.pdf'])
        ->and($file->user_id)->toBe($me->id)
        ->and($file->ai_message_id)->toBeNull()
        ->and($file->origin)->toBe(AiFile::ORIGIN_UPLOAD)
        ->and($response->json('files.0.id'))->toBe($file->remote_file_id);
});

test('files over the size limit or of an unsupported type are rejected before reaching the AI service', function (string $name, int $kilobytes) {
    $fake = fakeFilesAi();

    $this->actingAs(aiFilesUser())
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create($name, $kilobytes)]])
        ->assertUnprocessable();

    expect($fake->uploaded)->toBe([])->and(AiFile::count())->toBe(0);
})->with([
    'too large' => ['besar.pdf', 30 * 1024 + 1],
    'unsupported type' => ['program.exe', 10],
    'env file' => ['.env', 1],
]);

test('at most the configured number of files can wait for one message', function () {
    config(['ai_center.files.max_per_message' => 2]);
    $me = aiFilesUser();
    fakeFilesAi();
    AiFile::factory()->count(2)->create(['user_id' => $me->id]);

    $this->actingAs($me)
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create('c.pdf', 10)]])
        ->assertUnprocessable();
});

test('a refusal from the AI service is shown with its own message and nothing is saved', function (AiErrorCode $code) {
    fakeFilesAi($code);

    $this->actingAs(aiFilesUser())
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10)]])
        ->assertUnprocessable()
        ->assertJsonPath('code', $code->value)
        ->assertJsonPath('message', $code->message());

    expect(AiFile::count())->toBe(0);
})->with([AiErrorCode::FileSensitive, AiErrorCode::FileUnsupported, AiErrorCode::UpstreamTimeout]);

test('an upload that was not sent can be removed, but not someone else\'s', function () {
    $me = aiFilesUser();
    $fake = fakeFilesAi();
    $mine = AiFile::factory()->create(['user_id' => $me->id]);
    $theirs = AiFile::factory()->create();

    $this->actingAs($me)->deleteJson(route('ai-center.files.destroy', $theirs->remote_file_id))->assertNotFound();
    $this->actingAs($me)->deleteJson(route('ai-center.files.destroy', $mine->remote_file_id))->assertNoContent();

    expect(AiFile::find($mine->id))->toBeNull()
        ->and(AiFile::find($theirs->id))->not->toBeNull()
        ->and($fake->deletedFiles)->toBe([$mine->remote_file_id]);
});

test('sending a message hands the uploaded file ids to the AI and attaches the files to the message', function () {
    $me = aiFilesUser();
    $fake = fakeFilesAi();
    $file = AiFile::factory()->create(['user_id' => $me->id]);

    $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Ringkas file ini', 'file_ids' => [$file->remote_file_id]])
        ->streamedContent();

    $userMessage = AiMessage::where('content', 'Ringkas file ini')->sole();

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->fileIds)->toBe([$file->remote_file_id])
        ->and($file->fresh()->ai_message_id)->toBe($userMessage->id);
});

test('file ids that are not the sender\'s pending uploads are refused before anything is saved', function (string $case) {
    $me = aiFilesUser();
    $fake = fakeFilesAi();

    $fileId = match ($case) {
        'someone else' => AiFile::factory()->create()->remote_file_id,
        'already sent' => AiFile::factory()->create([
            'user_id' => $me->id,
            'ai_message_id' => AiMessage::factory()->create()->id,
        ])->remote_file_id,
        'unknown' => 'file_does_not_exist',
    };

    $conversationsBefore = AiConversation::count();

    $content = $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Ringkas file ini', 'file_ids' => [$fileId]])
        ->streamedContent();

    expect($content)->toContain('file_invalid')
        ->and($fake->requests)->toBe([])
        ->and(AiConversation::count())->toBe($conversationsBefore);
})->with(['someone else', 'already sent', 'unknown']);

test('a file the AI generates is saved on the answer and offered as a download', function () {
    $me = aiFilesUser();

    app()->instance(AiGateway::class, new class extends FakeAiGateway
    {
        public function stream(AiChatRequest $request): iterable
        {
            yield AiStreamEvent::delta('Ini laporannya.');
            yield AiStreamEvent::file('file_gen_1', 'laporan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            yield AiStreamEvent::done(model: 'fake', finishReason: 'STOP');
        }
    });

    $content = $this->actingAs($me)
        ->postJson(route('ai-center.messages.stream'), ['message' => 'Buat laporan excel'])
        ->streamedContent();

    $file = AiFile::sole();
    $answer = AiMessage::where('content', 'Ini laporannya.')->sole();

    expect($file->origin)->toBe(AiFile::ORIGIN_GENERATED)
        ->and($file->user_id)->toBe($me->id)
        ->and($file->ai_message_id)->toBe($answer->id)
        ->and($file->remote_file_id)->toBe('file_gen_1')
        ->and($content)->toContain('event: file')->toContain('laporan.xlsx')
        ->toContain(str_replace('/', '\/', route('ai-center.files.download', $file)));
});

test('a file can only be downloaded by its owner and only once attached to a message', function () {
    $me = aiFilesUser();
    fakeFilesAi();
    $message = AiMessage::factory()->assistant()->create();
    $mine = AiFile::factory()->generated()->create(['user_id' => $me->id, 'ai_message_id' => $message->id]);
    $theirs = AiFile::factory()->generated()->create(['ai_message_id' => $message->id]);
    $pending = AiFile::factory()->create(['user_id' => $me->id]);

    $response = $this->actingAs($me)->get(route('ai-center.files.download', $mine))->assertOk();

    expect($response->streamedContent())->toContain($mine->remote_file_id)
        ->and($response->headers->get('Content-Disposition'))->toContain('laporan.xlsx');

    $this->actingAs($me)->get(route('ai-center.files.download', $theirs))->assertNotFound();
    $this->actingAs($me)->get(route('ai-center.files.download', $pending))->assertNotFound();
});

test('a document is generated from structured data, saved for the user and downloadable without a chat', function () {
    $me = aiFilesUser();
    $fake = fakeFilesAi();

    $payload = ['format' => 'xlsx', 'filename' => 'laporan-q3', 'sheets' => [['name' => 'Ringkasan', 'rows' => [['A', 1]]]]];

    $response = $this->actingAs($me)
        ->postJson(route('ai-center.files.generate'), $payload)
        ->assertCreated()
        ->assertJsonPath('file.name', 'laporan-q3.xlsx');

    $file = AiFile::sole();

    expect($fake->generated)->toBe([$payload])
        ->and($file->user_id)->toBe($me->id)
        ->and($file->origin)->toBe(AiFile::ORIGIN_GENERATED)
        ->and($file->ai_message_id)->toBeNull()
        ->and($response->json('file.download_url'))->toBe(route('ai-center.files.download', $file));

    $this->actingAs($me)->get(route('ai-center.files.download', $file))->assertOk();
    $this->actingAs(aiFilesUser())->get(route('ai-center.files.download', $file))->assertNotFound();
});

test('document generation validates the request, the template ownership and the AI service answer', function () {
    $me = aiFilesUser();
    fakeFilesAi();

    $this->actingAs($me)->postJson(route('ai-center.files.generate'), ['format' => 'exe', 'filename' => 'a'])->assertUnprocessable();
    $this->actingAs($me)->postJson(route('ai-center.files.generate'), ['format' => 'docx'])->assertUnprocessable();

    $someoneElses = AiFile::factory()->create();
    $this->actingAs($me)
        ->postJson(route('ai-center.files.generate'), ['format' => 'docx', 'filename' => 'a', 'template_file_id' => $someoneElses->remote_file_id])
        ->assertNotFound();

    fakeFilesAi(AiErrorCode::FileSensitive);
    $this->actingAs($me)
        ->postJson(route('ai-center.files.generate'), ['format' => 'docx', 'filename' => 'a'])
        ->assertUnprocessable()
        ->assertJsonPath('code', AiErrorCode::FileSensitive->value);

    expect(AiFile::where('origin', AiFile::ORIGIN_GENERATED)->count())->toBe(0);
});

test('an image or PDF is previewed inline and only by its owner', function () {
    $me = aiFilesUser();
    fakeFilesAi();
    $pdf = AiFile::factory()->create(['user_id' => $me->id]);
    $svg = AiFile::factory()->create(['user_id' => $me->id, 'name' => 'logo.svg', 'mime_type' => 'image/svg+xml']);
    $xlsx = AiFile::factory()->generated()->create(['user_id' => $me->id]);
    $theirs = AiFile::factory()->create();

    $response = $this->actingAs($me)->get(route('ai-center.files.preview', $pdf))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $this->actingAs($me)->get(route('ai-center.files.preview', $svg))->assertStatus(415);
    $this->actingAs($me)->get(route('ai-center.files.preview', $xlsx))->assertStatus(415);
    $this->actingAs($me)->get(route('ai-center.files.preview', $theirs))->assertNotFound();
});

test('file sizes are labelled in readable units', function (int $bytes, string $label) {
    expect(AiFile::factory()->make(['size_bytes' => $bytes])->sizeLabel())->toBe($label);
})->with([[512, '512 B'], [279_000, '272,5 KB'], [5 * 1024 * 1024, '5,0 MB']]);

test('an upload answers with what the page needs to list and preview it', function () {
    fakeFilesAi();

    $this->actingAs(aiFilesUser())
        ->postJson(route('ai-center.files.store'), ['files' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')]])
        ->assertCreated()
        ->assertJsonPath('files.0.kind', 'pdf')
        ->assertJsonPath('files.0.download_url', null)
        ->assertJsonStructure(['files' => [['id', 'name', 'size_label', 'preview_url']]]);
});

test('the Nakama gateway posts the document to the generate endpoint and maps the answer', function () {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test', 'ai_center.nakama.api_key' => 'nk-super-secret']);

    Http::fake(['*/v1/files/generate' => Http::response([
        'id' => 'file_gen9', 'filename' => 'laporan.xlsx', 'mime_type' => 'application/vnd.ms-excel', 'size_bytes' => 2048,
    ], 201)]);

    $result = (new NakamaAiGateway)->generateDocument(['format' => 'xlsx', 'filename' => 'laporan']);

    expect($result)->toBe(['id' => 'file_gen9', 'name' => 'laporan.xlsx', 'mime_type' => 'application/vnd.ms-excel', 'size_bytes' => 2048]);

    Http::assertSent(fn ($request) => $request->url() === 'https://nakama-ai.test/v1/files/generate'
        && $request->hasHeader('X-API-Key', 'nk-super-secret')
        && $request['format'] === 'xlsx');
});

test('the Nakama gateway uploads multipart with the key and maps the service\'s answer', function () {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test', 'ai_center.nakama.api_key' => 'nk-super-secret']);

    Http::fake(['*/v1/files' => Http::response(['files' => [[
        'id' => 'file_0f1e', 'filename' => 'laporan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024,
    ]]], 201)]);

    $result = (new NakamaAiGateway)->uploadFiles([UploadedFile::fake()->create('laporan.pdf', 1, 'application/pdf')]);

    expect($result)->toBe([['id' => 'file_0f1e', 'name' => 'laporan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://nakama-ai.test/v1/files'
        && $request->hasHeader('X-API-Key', 'nk-super-secret')
        && $request->isMultipart()
        && ! str_contains($request->body(), 'nk-super-secret'));
});

test('the Nakama gateway maps upload errors to the file error codes', function (int $status, ?string $serviceCode, AiErrorCode $expected) {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test']);
    Http::fake(['*/v1/files' => Http::response(['error' => ['code' => $serviceCode, 'message' => 'detail']], $status)]);

    try {
        (new NakamaAiGateway)->uploadFiles([UploadedFile::fake()->create('a.pdf', 1)]);

        $code = null;
    } catch (AiFileUploadException $e) {
        $code = $e->errorCode;
    }

    expect($code)->toBe($expected);
})->with([
    [413, null, AiErrorCode::FileTooLarge],
    [415, null, AiErrorCode::FileUnsupported],
    [422, 'sensitive_data_detected', AiErrorCode::FileSensitive],
    [422, 'face_detected', AiErrorCode::FileSensitive],
    [422, 'validation_error', AiErrorCode::FileInvalid],
    [429, null, AiErrorCode::UpstreamQuota],
    [502, null, AiErrorCode::UpstreamError],
]);

test('the Nakama gateway sends file ids with the chat and turns document events into file events', function () {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test']);

    $sse = "event: document\ndata: ".json_encode(['file_id' => 'file_gen_1', 'filename' => 'laporan.xlsx', 'mime_type' => 'application/vnd.ms-excel'])."\n\n"
        ."event: done\ndata: ".json_encode(['message' => ['tokens' => []]])."\n\n";

    Http::fake(['*/v1/chat' => Http::response($sse)]);

    $request = new AiChatRequest(requestId: 'r', userId: 1, userName: 'Budi', conversationId: 1, message: 'Hai', fileIds: ['file_a']);
    $events = iterator_to_array((new NakamaAiGateway)->stream($request), false);

    expect($events[0]->isFile())->toBeTrue()
        ->and($events[0]->remoteFileId)->toBe('file_gen_1')
        ->and($events[0]->fileName)->toBe('laporan.xlsx');

    Http::assertSent(fn ($request) => ($request->data()['file_ids'] ?? null) === ['file_a']);
});

test('the Nakama gateway downloads through the key and follows the presigned redirect without it', function () {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test', 'ai_center.nakama.api_key' => 'nk-super-secret']);

    Http::fake([
        'nakama-ai.test/v1/files/file_1/content' => Http::response('', 307, ['Location' => 'https://minio.test/bucket/file_1?sig=abc']),
        'minio.test/*' => Http::response('isi file'),
    ]);

    $stream = (new NakamaAiGateway)->downloadFile('file_1');

    expect((string) $stream)->toBe('isi file');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://minio.test') && ! $request->hasHeader('X-API-Key'));
});

test('the Nakama gateway returns nothing for a file that is gone', function () {
    config(['ai_center.nakama.url' => 'https://nakama-ai.test']);
    Http::fake(['*' => Http::response(['error' => ['code' => 'gone']], 410)]);

    expect((new NakamaAiGateway)->downloadFile('file_1'))->toBeNull();
});

test('the chat page shows the attach button and the files saved on a conversation', function () {
    $me = aiFilesUser();
    $conversation = AiConversation::factory()->for($me)->create();
    $question = AiMessage::factory()->for($conversation, 'conversation')->create(['content' => 'Ringkas laporan']);
    $answer = AiMessage::factory()->assistant()->for($conversation, 'conversation')->create(['content' => 'Selesai']);
    AiFile::factory()->create(['user_id' => $me->id, 'ai_message_id' => $question->id, 'name' => 'notulen.pdf']);
    $generated = AiFile::factory()->generated()->create(['user_id' => $me->id, 'ai_message_id' => $answer->id]);

    $this->actingAs($me)->get(route('ai-center'))->assertOk()->assertSee('Lampirkan file');

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->assertSee('notulen.pdf')
        ->assertSee('laporan.xlsx')
        ->assertSee(route('ai-center.files.download', $generated));
});
