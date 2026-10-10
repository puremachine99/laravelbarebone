<?php

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiDataQueryLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\FakeAiGateway;
use App\Support\ResourcePermissions;
use Spatie\Permission\Models\Permission;

function dataModeUser(array $permissions): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), ['manage-ai-center', ...$permissions]));

    return $user;
}

function dataModeAi(string $planReply = '{"queries":[]}'): FakeAiGateway
{
    $fake = new FakeAiGateway;
    $fake->planReply = $planReply;
    app()->instance(AiGateway::class, $fake);

    return $fake;
}

function askAi(User $user, string $message): string
{
    return test()->actingAs($user)
        ->postJson(route('ai-center.messages.stream'), ['message' => $message])
        ->streamedContent();
}

const FO_TOP_PLAN = '{"queries":[{"dataset":"fo_achievement","group_by":["employee"],"all_time":true,"metrics":["nilai_closing"],"sort":"nilai_closing","limit":5}]}';

beforeEach(function () {
    $department = Department::create(['name' => 'Departemen Finance']);
    $user = User::factory()->create(['name' => 'Putri Funding', 'department_id' => $department->id]);
    $officer = Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'nik' => '3171234567890001']);
    FoFunnel::factory()->create(['employee_id' => $officer->id, 'stage' => 'closing', 'tgl_leads' => '2026-01-05', 'tgl_closing' => '2026-01-20', 'nominal_investasi' => 75_000_000]);
});

test('a user without data access keeps the plain chat', function () {
    $fake = dataModeAi(FO_TOP_PLAN);

    $content = askAi(dataModeUser([]), 'Siapa FO terbaik?');

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->purpose)->toBe(AiChatRequest::PURPOSE_CHAT)
        ->and($fake->requests[0]->systemPrompt)->toBeNull()
        ->and($content)->not->toContain('Sumber data');
    expect(AiDataQueryLog::count())->toBe(0);
});

test('a data question is planned, run as the user, handed to the AI and sourced under the answer', function () {
    $fake = dataModeAi(FO_TOP_PLAN);
    $user = dataModeUser(['ai-data-fo-achievement.view', 'ai-data-all-departments.view']);

    $content = askAi($user, 'Siapa FO dengan nilai closing tertinggi sepanjang waktu?');

    [$plan, $answer] = $fake->requests;

    expect($plan->purpose)->toBe(AiChatRequest::PURPOSE_DATA_PLAN)
        ->and($plan->systemPrompt)->toContain('fo_achievement')
        ->and($answer->purpose)->toBe(AiChatRequest::PURPOSE_CHAT)
        ->and($answer->systemPrompt)->toBe(config('ai_center.data.system_prompt'))
        ->and($answer->message)->toContain('Siapa FO dengan nilai closing tertinggi')
        ->and($answer->message)->toContain('DATA DARI SISTEM NAKAMA')
        ->and($answer->message)->toContain('Putri Funding')
        ->and($answer->message)->toContain('75000000.0')
        ->and($answer->message)->not->toContain('3171234567890001');

    expect($content)->toContain('Sumber data: Capaian Funding Officer');

    $conversation = AiConversation::sole();
    [$question, $reply] = $conversation->messages()->oldest('id')->get();

    expect($question->content)->toBe('Siapa FO dengan nilai closing tertinggi sepanjang waktu?')
        ->and($reply->role)->toBe(AiMessageRole::Assistant)
        ->and($reply->content)->toContain('Sumber data: Capaian Funding Officer');

    expect(AiDataQueryLog::where('user_id', $user->id)->sole()->outcome)->toBe('ok');
});

test('the planner only sees the datasets the user may use', function () {
    $fake = dataModeAi();

    askAi(dataModeUser(['ai-data-ai-usage.view']), 'Berapa pemakaian AI bulan ini?');

    expect($fake->requests[0]->systemPrompt)->toContain('ai_usage')
        ->not->toContain('fo_achievement');
});

test('a dataset the user may not use is refused and the AI is told so', function () {
    $fake = dataModeAi(FO_TOP_PLAN);
    $user = dataModeUser(['ai-data-ai-usage.view']);

    askAi($user, 'Siapa FO terbaik?');

    expect($fake->requests[1]->message)->toContain('Anda tidak punya akses ke data ini.')
        ->not->toContain('Putri Funding');
    expect(AiDataQueryLog::where('user_id', $user->id)->sole()->outcome)->toBe('denied');
});

test('an invalid query is reported to the AI instead of failing the answer', function () {
    $fake = dataModeAi('{"queries":[{"dataset":"fo_achievement","group_by":["nik"]}]}');

    $content = askAi(dataModeUser(['ai-data-fo-achievement.view']), 'Tampilkan NIK semua FO');

    expect($fake->requests[1]->message)->toContain('Permintaan data tidak valid')
        ->not->toContain('3171234567890001');
    expect($content)->toContain('event: done');
});

test('a plan wrapped in a code fence is still understood', function () {
    $fake = dataModeAi("Berikut rencananya:\n```json\n".FO_TOP_PLAN."\n```");

    askAi(dataModeUser(['ai-data-fo-achievement.view', 'ai-data-all-departments.view']), 'Siapa FO terbaik?');

    expect($fake->requests[1]->message)->toContain('Putri Funding');
});

test('an unusable plan falls back to a normal answer without data', function () {
    $fake = dataModeAi('maaf, saya tidak mengerti');

    $content = askAi(dataModeUser(['ai-data-fo-achievement.view']), 'Halo, apa kabar?');

    expect($fake->requests[1]->message)->toBe('Halo, apa kabar?')
        ->and($fake->requests[1]->systemPrompt)->toBe(config('ai_center.data.system_prompt'))
        ->and($content)->not->toContain('Sumber data')
        ->and($content)->toContain('event: done');
});

test('no more queries than the per-message maximum are run', function () {
    $query = '{"dataset":"fo_achievement","group_by":["employee"]}';
    dataModeAi('{"queries":['.implode(',', array_fill(0, 5, $query)).']}');
    $user = dataModeUser(['ai-data-fo-achievement.view']);

    askAi($user, 'Bandingkan semuanya');

    expect(AiDataQueryLog::where('user_id', $user->id)->count())->toBe(config('ai_center.data.max_queries_per_message'));
});

test('the hourly query limit stops further queries', function () {
    config(['ai_center.data.queries_per_hour' => 1]);
    $query = '{"dataset":"fo_achievement","group_by":["employee"]}';
    $fake = dataModeAi('{"queries":['.$query.','.$query.']}');
    $user = dataModeUser(['ai-data-fo-achievement.view']);

    askAi($user, 'Dua query');

    expect($fake->requests[1]->message)->toContain('Batas query data per jam')
        ->and(AiDataQueryLog::where('user_id', $user->id)->count())->toBe(1);
});

test('data mode can be switched off', function () {
    config(['ai_center.data.enabled' => false]);
    $fake = dataModeAi(FO_TOP_PLAN);

    askAi(dataModeUser(['ai-data-fo-achievement.view']), 'Siapa FO terbaik?');

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->systemPrompt)->toBeNull();
});
