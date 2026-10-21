<?php

use App\Livewire\Ai\AiCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AiCenter\NakamaMentoring;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config([
        'ai_center.nakama.url' => 'https://nakama-ai.test',
        'ai_center.nakama.api_key' => 'nk-super-secret',
    ]);
});

function mentoringUser(): User
{
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    $user->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    return $user;
}

/**
 * @return array<string, mixed>
 */
function mappingSession(string $id, string $remoteUserId, string $status = 'active'): array
{
    return [
        'id' => $id,
        'user_id' => $remoteUserId,
        'display_name' => 'Budi Santoso',
        'anonymous' => false,
        'unit' => ['key' => 'finance', 'name' => 'Finance', 'icon' => 'payments', 'emoji' => '💰', 'color' => '#1B5E20', 'soft_color' => '#D2F5DC'],
        'status' => $status,
        'progress' => ['step' => 2, 'percent' => 15],
        'prompt' => [],
        'summary' => [],
        'messages' => [['id' => 'msg_1', 'role' => 'assistant', 'kind' => 'question', 'text' => 'Apa output kerjamu?', 'ui' => ['type' => 'list']]],
        'created_at' => '2026-10-21T08:00:00Z',
        'updated_at' => '2026-10-21T08:05:00Z',
    ];
}

/**
 * Fakes the Nakama mapping API: `map_mine` belongs to $user, `map_theirs` to someone else.
 */
function fakeMappingApi(User $user): void
{
    $mine = 'smartid-'.$user->id;

    Http::fake(function (Request $request) use ($mine) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/v1/mapping/units' => Http::response(['items' => [['key' => 'finance', 'name' => 'Finance']]]),
            $path === '/v1/mapping/sessions' && $request->method() === 'GET' => Http::response(['items' => [
                mappingSession('map_mine', $mine, 'paused'),
                mappingSession('map_theirs', 'smartid-999'),
            ], 'total' => 2, 'limit' => 20, 'offset' => 0]),
            $path === '/v1/mapping/sessions' => Http::response(mappingSession('map_new', $request['user_id'])),
            $path === '/v1/mapping/sessions/map_mine' => Http::response(mappingSession('map_mine', $mine)),
            $path === '/v1/mapping/sessions/map_theirs' => Http::response(mappingSession('map_theirs', 'smartid-999')),
            $path === '/v1/mapping/sessions/map_done' => Http::response(mappingSession('map_done', $mine, 'completed')),
            $path === '/v1/mapping/sessions/map_done/export' => Http::response('# Ringkasan selesai', 200),
            $path === '/v1/mapping/sessions/map_mine/messages' => Http::response([
                'session' => mappingSession('map_mine', $mine),
                'messages' => [['role' => 'assistant', 'kind' => 'question', 'text' => 'Berapa lama?', 'ui' => ['type' => 'duration']]],
            ]),
            $path === '/v1/mapping/sessions/map_mine/pause' => Http::response(mappingSession('map_mine', $mine, 'paused')),
            $path === '/v1/mapping/sessions/map_mine/export' => Http::response("# Ringkasan\n\nOutput: Laporan kas", 200, ['Content-Type' => 'text/markdown']),
            default => Http::response(['error' => ['message' => 'not found']], 404),
        };
    });
}

test('the AI Center shows the chat tab by default and the pendampingan desk on its tab', function () {
    $user = mentoringUser();

    $this->actingAs($user)->get(route('ai-center'))
        ->assertOk()
        ->assertSee('Pendampingan')
        ->assertSee('Jawaban AI bisa keliru')
        ->assertDontSee('aiMentoringDesk');

    $this->actingAs($user)->get(route('ai-center', ['tab' => 'pendampingan']))
        ->assertOk()
        ->assertSee('aiMentoringDesk')
        ->assertSee('Selamat Datang di Sesi Ngobrol Santai Pemetaan Kerja')
        ->assertDontSee('Jawaban AI bisa keliru')
        ->assertDontSee('nk-super-secret');
});

test('switching tabs only accepts the two known tabs', function () {
    Livewire::actingAs(mentoringUser())
        ->test(AiCenter::class)
        ->assertSet('tab', 'chat')
        ->call('switchTab', 'pendampingan')
        ->assertSet('tab', 'pendampingan')
        ->call('switchTab', 'anything-else')
        ->assertSet('tab', 'chat');
});

test('the pendampingan endpoints need the AI Center permission', function () {
    $this->getJson(route('ai-center.mentoring.units'))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('ai-center.mentoring.units'))->assertForbidden();
});

test('units are relayed with the API key kept on the server', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->getJson(route('ai-center.mentoring.units'))
        ->assertOk()
        ->assertJsonPath('items.0.key', 'finance')
        ->assertDontSee('nk-super-secret');

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'nk-super-secret'));
});

test('a new session is tagged with the logged-in user id and name', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.store'), ['unit' => 'finance'])
        ->assertCreated()
        ->assertJsonPath('id', 'map_new');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['user_id'] === 'smartid-'.$user->id
        && $request['name'] === 'Budi Santoso'
        && $request['anonymous'] === false);
});

test('a session can never be made anonymous or renamed from the browser', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.store'), [
        'unit' => 'finance',
        'name' => 'Orang Lain',
        'anonymous' => true,
        'user_id' => 'smartid-1',
    ])->assertCreated();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['user_id'] === 'smartid-'.$user->id
        && $request['name'] === 'Budi Santoso'
        && $request['anonymous'] === false);
});

test('the desk shows who the session is recorded for, with no anonymous option', function () {
    $this->actingAs(mentoringUser())->get(route('ai-center', ['tab' => 'pendampingan']))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('BS')
        ->assertDontSee('anonim');
});

test('the history only lists my own sessions', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.index'))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', 'map_mine');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'user_id=smartid-'.$user->id));
});

test('I can open my own session but not someone else\'s', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.show', 'map_mine'))
        ->assertOk()
        ->assertJsonPath('messages.0.text', 'Apa output kerjamu?');

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.show', 'map_theirs'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Sesi pendampingan tidak ditemukan.');
});

test('an answer is relayed to my session', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.answer', 'map_mine'), ['items' => ['Laporan kas harian']])
        ->assertOk()
        ->assertJsonPath('messages.0.ui.type', 'duration');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/map_mine/messages') && $request['items'] === ['Laporan kas harian']);
});

test('answers to someone else\'s session are never sent', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.answer', 'map_theirs'), ['text' => 'Halo'])
        ->assertNotFound();
    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.pause', 'map_theirs'))
        ->assertNotFound();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

test('an empty answer is refused before calling the service', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.answer', 'map_mine'), ['text' => '  '])
        ->assertUnprocessable();

    Http::assertNothingSent();
});

test('the session summary downloads as a file', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->get(route('ai-center.mentoring.sessions.export', ['sessionId' => 'map_mine', 'format' => 'markdown']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="ringkasan-pendampingan-mapmine.md"')
        ->assertSee('Output: Laporan kas');

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.export', ['sessionId' => 'map_mine', 'format' => 'exe']))
        ->assertUnprocessable();
});

test('an unreachable service is reported without leaking details', function () {
    $user = mentoringUser();
    Http::fake(fn () => throw new ConnectionException('cURL error 28 to https://nakama-ai.test'));

    $this->actingAs($user)->getJson(route('ai-center.mentoring.units'))
        ->assertStatus(504)
        ->assertJsonPath('message', 'Layanan pendampingan sedang tidak dapat dihubungi. Coba lagi sebentar lagi.')
        ->assertDontSee('nakama-ai.test');
});

test('a finished session stays readable but can no longer be answered or paused', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.show', 'map_done'))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('messages.0.text', 'Apa output kerjamu?');

    $this->actingAs($user)->get(route('ai-center.mentoring.sessions.export', ['sessionId' => 'map_done', 'format' => 'markdown']))
        ->assertOk()
        ->assertSee('Ringkasan selesai');

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.answer', 'map_done'), ['text' => 'Ubah jawaban'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sesi ini sudah selesai dan hanya bisa dibaca.');
    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.pause', 'map_done'))
        ->assertUnprocessable();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

test('the desk explains that the history is read-only', function () {
    $this->actingAs(mentoringUser())->get(route('ai-center', ['tab' => 'pendampingan']))
        ->assertOk()
        ->assertSee('Riwayat pendampinganmu')
        ->assertSee('Riwayat hanya bisa dibaca, tidak bisa diubah.');
});

test('the unit comes from the user department and cannot be changed from the browser', function () {
    $user = mentoringUser();
    $user->update(['department_id' => Department::create(['name' => 'Departemen Finance', 'code' => 'FF'])->id]);
    fakeMappingApi($user);

    expect(app(NakamaMentoring::class)->assignedUnitFor($user->fresh()))->toBe('finance');

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.store'), ['unit' => 'digital_transformation'])
        ->assertCreated();
    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.store'))
        ->assertCreated();

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && $request['unit'] !== 'finance');

    $this->actingAs($user)->get(route('ai-center', ['tab' => 'pendampingan']))
        ->assertSee('assignedUnit: \'finance\'', false);
});

test('Super Admins and departments without a unit pick the unit themselves', function () {
    $superAdmin = mentoringUser();
    $superAdmin->update(['department_id' => Department::create(['name' => 'Departemen Finance', 'code' => 'FF'])->id]);
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $director = mentoringUser();
    $director->update(['department_id' => Department::create(['name' => 'BoD/BoA/C-Level', 'code' => 'C'])->id]);

    $mentoring = app(NakamaMentoring::class);
    expect($mentoring->assignedUnitFor($superAdmin->fresh()))->toBeNull()
        ->and($mentoring->assignedUnitFor($director->fresh()))->toBeNull()
        ->and($mentoring->assignedUnitFor(mentoringUser()))->toBeNull();

    fakeMappingApi($director);

    $this->actingAs($director)->postJson(route('ai-center.mentoring.sessions.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit');
    $this->actingAs($director)->postJson(route('ai-center.mentoring.sessions.store'), ['unit' => 'people_and_culture'])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['unit'] === 'people_and_culture');
});

test('an unfinished session can be cancelled and then disappears from the history', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.cancel', 'map_mine'))
        ->assertOk()
        ->assertJsonPath('cancelled', true);

    $this->assertDatabaseHas('ai_mentoring_cancellations', [
        'user_id' => $user->id,
        'remote_session_id' => 'map_mine',
        'unit_key' => 'finance',
    ]);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/map_mine/pause'));

    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.index'))
        ->assertOk()
        ->assertJsonCount(0, 'items');
    $this->actingAs($user)->getJson(route('ai-center.mentoring.sessions.show', 'map_mine'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Sesi pendampingan ini sudah dibatalkan.');
    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.answer', 'map_mine'), ['text' => 'Lanjut'])
        ->assertNotFound();
    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.cancel', 'map_mine'))
        ->assertNotFound();

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/map_mine/messages'));
    expect($user->aiMentoringCancellations()->count())->toBe(1);
});

test('a finished session cannot be cancelled', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.cancel', 'map_done'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sesi yang sudah selesai tidak bisa dibatalkan.');

    $this->assertDatabaseCount('ai_mentoring_cancellations', 0);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

test('someone else\'s session cannot be cancelled', function () {
    $user = mentoringUser();
    fakeMappingApi($user);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.cancel', 'map_theirs'))
        ->assertNotFound();

    $this->assertDatabaseCount('ai_mentoring_cancellations', 0);
});

test('a paused session is cancelled without calling the service again', function () {
    $user = mentoringUser();
    Http::fake([
        'nakama-ai.test/v1/mapping/sessions/map_paused' => Http::response(mappingSession('map_paused', 'smartid-'.$user->id, 'paused')),
    ]);

    $this->actingAs($user)->postJson(route('ai-center.mentoring.sessions.cancel', 'map_paused'))->assertOk();

    Http::assertSentCount(1);
    $this->assertDatabaseHas('ai_mentoring_cancellations', ['remote_session_id' => 'map_paused']);
});

test('the unit is taken from the employee department when the user copy was never synced', function () {
    $user = mentoringUser();
    Employee::create(['user_id' => $user->id, 'department_id' => Department::create(['name' => 'Departemen Digital Transformation', 'code' => 'TEC'])->id]);
    User::query()->whereKey($user->id)->update(['department_id' => null]);

    expect(app(NakamaMentoring::class)->assignedUnitFor($user->fresh()))->toBe('digital_transformation');

    fakeMappingApi($user);

    $this->actingAs($user->fresh())->postJson(route('ai-center.mentoring.sessions.store'), ['unit' => 'finance'])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['unit'] === 'digital_transformation');
});
