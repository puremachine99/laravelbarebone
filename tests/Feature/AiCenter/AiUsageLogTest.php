<?php

use App\Enums\AiErrorCode;
use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use App\Models\AiUsageLog;
use App\Models\AiUsageSummary;
use App\Models\Department;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\AiStreamEvent;
use App\Services\AiCenter\FakeAiGateway;
use App\Support\ResourcePermissions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

function usageLogUser(array $permissions = []): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create([
        'name' => 'Rina Finance',
        'department_id' => Department::create(['name' => 'Finance'])->id,
    ]);
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), ['manage-ai-center', ...$permissions]));

    return $user;
}

function usageLogAi(?AiErrorCode $failWith = null): FakeAiGateway
{
    $fake = new FakeAiGateway(failWith: $failWith);
    app()->instance(AiGateway::class, $fake);

    return $fake;
}

function sendUsageMessage(User $user, string $message = 'Halo AI, tolong bantu saya'): void
{
    test()->actingAs($user)
        ->postJson(route('ai-center.messages.stream'), ['message' => $message])
        ->streamedContent();
}

test('the usage log has no column for message text', function () {
    expect(Schema::getColumnListing('ai_usage_logs'))
        ->not->toContain('content')
        ->not->toContain('message');
});

test('an answered message logs its tokens, model, latency and user', function () {
    $user = usageLogUser();
    usageLogAi();

    sendUsageMessage($user, str_repeat('a', 40));

    $log = AiUsageLog::sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->user_name)->toBe('Rina Finance')
        ->and($log->department)->toBe('Finance')
        ->and($log->kind)->toBe(AiUsageKind::Chat)
        ->and($log->outcome)->toBe(AiUsageOutcome::Ok)
        ->and($log->model)->toBe('fake')
        ->and($log->finish_reason)->toBe('STOP')
        ->and($log->prompt_tokens)->toBe(10)
        ->and($log->output_tokens)->toBe(60)
        ->and($log->total_tokens)->toBe(70)
        ->and($log->first_token_ms)->not->toBeNull()
        ->and($log->duration_ms)->not->toBeNull()
        ->and($log->cost_usd)->toBeNull();
});

test('the cost is estimated from the configured token prices', function () {
    config(['ai_center.pricing.input_per_1m' => 0.5, 'ai_center.pricing.output_per_1m' => 2]);
    usageLogAi();

    sendUsageMessage(usageLogUser(), str_repeat('a', 40));

    // 10 × 0.5 / 1M + 60 × 2 / 1M
    expect((float) AiUsageLog::sole()->cost_usd)->toBe(0.000125);
});

test('a failed answer is logged as an error with its code', function () {
    usageLogAi(AiErrorCode::UpstreamTimeout);

    sendUsageMessage(usageLogUser());

    $log = AiUsageLog::sole();

    expect($log->outcome)->toBe(AiUsageOutcome::Error)
        ->and($log->error_code)->toBe('upstream_timeout')
        ->and($log->total_tokens)->toBe(0);
});

test('a refused message is logged as denied without calling the AI', function () {
    config(['ai_center.limits.max_input_chars' => 5]);
    $fake = usageLogAi();

    sendUsageMessage(usageLogUser(), 'Pesan yang terlalu panjang');

    expect($fake->requests)->toBeEmpty();
    expect(AiUsageLog::sole())
        ->outcome->toBe(AiUsageOutcome::Denied)
        ->error_code->toBe('input_too_long');
});

test('the hidden data-planning call gets its own log row', function () {
    usageLogAi();

    sendUsageMessage(usageLogUser(['ai-data-ai-usage.view']), 'Berapa pemakaian AI bulan ini?');

    expect(AiUsageLog::where('kind', AiUsageKind::DataPlan)->sole()->outcome)->toBe(AiUsageOutcome::Ok)
        ->and(AiUsageLog::where('kind', AiUsageKind::Chat)->count())->toBe(1);
});

test('the weekly summary totals the previous Monday–Sunday', function () {
    $this->travelTo('2026-10-13 08:00:00');

    AiUsageLog::factory()->create(['department' => 'Finance', 'prompt_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150, 'cost_usd' => 0.01, 'created_at' => '2026-10-05 00:00:00']);
    AiUsageLog::factory()->create(['department' => 'Technology', 'prompt_tokens' => 300, 'output_tokens' => 100, 'total_tokens' => 400, 'cost_usd' => 0.02, 'created_at' => '2026-10-11 23:59:00']);
    AiUsageLog::factory()->failed()->create(['created_at' => '2026-10-08 10:00:00']);
    AiUsageLog::factory()->create(['created_at' => '2026-10-04 23:59:59']);
    AiUsageLog::factory()->create(['created_at' => '2026-10-12 00:00:00']);

    $this->artisan('ai-usage:summarize', ['period' => 'weekly'])->assertSuccessful();

    $summary = AiUsageSummary::sole();

    expect($summary->period)->toBe('weekly')
        ->and($summary->period_start->toDateString())->toBe('2026-10-05')
        ->and($summary->period_end->toDateString())->toBe('2026-10-11')
        ->and($summary->requests)->toBe(3)
        ->and($summary->ok_count)->toBe(2)
        ->and($summary->error_count)->toBe(1)
        ->and($summary->active_users)->toBe(3)
        ->and($summary->prompt_tokens)->toBe(400)
        ->and($summary->total_tokens)->toBe(550)
        ->and((float) $summary->cost_usd)->toBe(0.03)
        ->and($summary->breakdown['by_department'][0])->toMatchArray(['name' => 'Technology', 'requests' => 1, 'total_tokens' => 400]);
});

test('the monthly summary covers the previous month and re-running it overwrites the row', function () {
    $this->travelTo('2026-11-01 00:45:00');

    AiUsageLog::factory()->create(['total_tokens' => 1000, 'created_at' => '2026-10-01 00:00:00']);
    AiUsageLog::factory()->create(['total_tokens' => 500, 'created_at' => '2026-10-31 23:00:00']);
    AiUsageLog::factory()->create(['total_tokens' => 9999, 'created_at' => '2026-11-01 00:10:00']);

    $this->artisan('ai-usage:summarize', ['period' => 'monthly'])->assertSuccessful();
    AiUsageLog::factory()->create(['total_tokens' => 1, 'created_at' => '2026-10-15 00:00:00']);
    $this->artisan('ai-usage:summarize', ['period' => 'monthly'])->assertSuccessful();

    $summary = AiUsageSummary::sole();

    expect($summary->period_start->toDateString())->toBe('2026-10-01')
        ->and($summary->period_end->toDateString())->toBe('2026-10-31')
        ->and($summary->requests)->toBe(3)
        ->and($summary->total_tokens)->toBe(1501);
});

test('a specific period can be summarized with --date', function () {
    AiUsageLog::factory()->create(['total_tokens' => 42, 'created_at' => '2026-08-20 12:00:00']);

    $this->artisan('ai-usage:summarize', ['period' => 'monthly', '--date' => '2026-08-05'])->assertSuccessful();

    expect(AiUsageSummary::sole())
        ->period_start->toDateString()->toBe('2026-08-01')
        ->total_tokens->toBe(42);
});

test('an unknown period is rejected', function () {
    $this->artisan('ai-usage:summarize', ['period' => 'daily'])->assertFailed();
});

test('raw logs are pruned after 3 months and summaries after 1 year', function () {
    $this->travelTo('2026-10-13 01:30:00');

    $oldLog = AiUsageLog::factory()->create(['created_at' => '2026-07-12 23:00:00']);
    $recentLog = AiUsageLog::factory()->create(['created_at' => '2026-07-14 00:00:00']);

    $summary = fn (string $start, string $end) => AiUsageSummary::create([
        'period' => 'monthly', 'period_start' => $start, 'period_end' => $end, 'breakdown' => [],
    ]);
    $oldSummary = $summary('2025-09-01', '2025-09-30');
    $recentSummary = $summary('2025-10-01', '2025-10-31');

    $this->artisan('model:prune', ['--model' => [AiUsageLog::class, AiUsageSummary::class]])->assertSuccessful();

    expect(AiUsageLog::pluck('id')->all())->toBe([$recentLog->id])
        ->and(AiUsageSummary::pluck('id')->all())->toBe([$recentSummary->id])
        ->and(AiUsageLog::find($oldLog->id))->toBeNull()
        ->and(AiUsageSummary::find($oldSummary->id))->toBeNull();
});

test('the types of the files the AI generated are logged, without their names', function () {
    $user = usageLogUser();

    app()->instance(AiGateway::class, new class extends FakeAiGateway
    {
        public function stream(AiChatRequest $request): iterable
        {
            yield AiStreamEvent::delta('Ini filenya.');
            yield AiStreamEvent::file('file_1', 'Daftar-Karyawan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            yield AiStreamEvent::file('file_2', 'Rekap.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            yield AiStreamEvent::file('file_3', 'gambar', 'image/jpeg');
            yield AiStreamEvent::done(model: 'fake', finishReason: 'STOP');
        }
    });

    sendUsageMessage($user, 'Buatkan excel dan gambar');

    $log = AiUsageLog::sole();

    expect($log->generated_file_types)->toBe(['xlsx', 'jpeg'])
        ->and(json_encode($log->getAttributes()))->not->toContain('Daftar-Karyawan');
});

test('an answer without files logs no file types', function () {
    $user = usageLogUser();
    usageLogAi();

    sendUsageMessage($user);

    expect(AiUsageLog::sole()->generated_file_types)->toBeNull();
});
