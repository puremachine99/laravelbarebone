<?php

use App\Enums\UserStatus;
use App\Models\AiConversation;
use App\Models\AiDataQueryLog;
use App\Models\AiMessage;
use App\Models\Department;
use App\Models\User;
use App\Services\AiData\DataQueryService;
use App\Services\AiData\DatasetCatalog;
use App\Support\ResourcePermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function aiDataUser(array $permissions, ?Department $department = null, array $attributes = []): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create(['department_id' => $department?->id, ...$attributes]);
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), $permissions));

    return $user;
}

/**
 * Creates $people users in $department, each sending $questionsEach AI questions at $at.
 *
 * @return array<int, User>
 */
function aiUsage(Department $department, int $people, int $questionsEach, string $at): array
{
    return collect(range(1, $people))->map(function () use ($department, $questionsEach, $at) {
        $user = User::factory()->create(['department_id' => $department->id]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        AiMessage::factory()->count($questionsEach)->create(['ai_conversation_id' => $conversation->id, 'created_at' => $at]);
        AiMessage::factory()->assistant()->count($questionsEach)->create(['ai_conversation_id' => $conversation->id, 'created_at' => $at]);

        return $user;
    })->all();
}

const AI_DATA_FULL = ['manage-ai-center', 'ai-data-ai-usage.view', 'ai-data-all-departments.view'];

beforeEach(function () {
    $this->service = app(DataQueryService::class);
    $this->finance = Department::create(['name' => 'Finance']);
    $this->tech = Department::create(['name' => 'Technology']);
});

test('groups usage per department and hides groups smaller than the minimum', function () {
    aiUsage($this->finance, 5, 2, '2026-05-10 09:00:00');
    aiUsage($this->tech, 2, 7, '2026-05-10 09:00:00');

    $result = $this->service->run(aiDataUser(AI_DATA_FULL), [
        'dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05',
    ]);

    expect($result['rows'])->toBe([['department' => 'Finance', 'questions' => 10, 'active_users' => 5]])
        ->and($result['suppressed_rows'])->toBe(1)
        ->and($result['scope'])->toBe('all_departments')
        ->and($result['notes'][0])->toContain('kurang dari 5 orang');
});

test('compares with the previous period and computes the change on the server', function () {
    aiUsage($this->finance, 5, 2, '2026-04-15 09:00:00');
    aiUsage($this->finance, 5, 3, '2026-05-15 09:00:00');

    $result = $this->service->run(aiDataUser(AI_DATA_FULL), [
        'dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05', 'compare_previous' => true, 'metrics' => ['questions'],
    ]);

    expect($result['compared_to'])->toBe(['from' => '2026-04', 'to' => '2026-04'])
        ->and($result['rows'])->toBe([[
            'department' => 'Finance',
            'questions' => 15,
            'questions_previous' => 10,
            'questions_delta' => 5,
            'questions_delta_pct' => 50.0,
        ]]);
});

test('groups by month across a multi-month period', function () {
    config(['ai_datasets.min_group_size' => 1]);
    aiUsage($this->finance, 1, 2, '2026-03-05 09:00:00');
    aiUsage($this->finance, 1, 4, '2026-04-05 09:00:00');

    $result = $this->service->run(aiDataUser(AI_DATA_FULL), [
        'dataset' => 'ai_usage', 'group_by' => ['month'], 'from' => '2026-03', 'to' => '2026-04', 'metrics' => ['questions'],
    ]);

    expect($result['rows'])->toBe([['month' => '2026-03', 'questions' => 2], ['month' => '2026-04', 'questions' => 4]]);
});

test('a user without the all-departments permission only sees their own department', function () {
    config(['ai_datasets.min_group_size' => 1]);
    aiUsage($this->finance, 2, 1, '2026-05-10 09:00:00');
    aiUsage($this->tech, 3, 1, '2026-05-10 09:00:00');
    $financeUser = aiDataUser(['manage-ai-center', 'ai-data-ai-usage.view'], $this->finance);

    $result = $this->service->run($financeUser, ['dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05']);

    expect($result['scope'])->toBe('own_department')
        ->and(collect($result['rows'])->pluck('department')->all())->toBe(['Finance']);

    $widened = $this->service->run($financeUser, [
        'dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05', 'filters' => ['department' => 'Technology'],
    ]);

    expect($widened['rows'])->toBe([]);
});

test('a scoped user without a department gets no rows', function () {
    config(['ai_datasets.min_group_size' => 1]);
    aiUsage($this->finance, 2, 1, '2026-05-10 09:00:00');

    $result = $this->service->run(aiDataUser(['manage-ai-center', 'ai-data-ai-usage.view']), [
        'dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05',
    ]);

    expect($result['rows'])->toBe([]);
});

test('denies a user without the dataset permission, hides it from their catalog and logs the attempt', function () {
    $user = aiDataUser(['manage-ai-center']);

    expect(app(DatasetCatalog::class)->describeFor($user))->toBe([]);
    expect(fn () => $this->service->run($user, ['dataset' => 'ai_usage']))->toThrow(AuthorizationException::class);
    expect(AiDataQueryLog::where('user_id', $user->id)->value('outcome'))->toBe('denied');
});

test('denies a user without AI Center access even with the dataset permission', function () {
    $user = aiDataUser(['ai-data-ai-usage.view']);

    expect(fn () => $this->service->run($user, ['dataset' => 'ai_usage']))->toThrow(AuthorizationException::class);
});

test('denies a suspended user', function () {
    $user = aiDataUser(AI_DATA_FULL, attributes: ['status' => UserStatus::Suspended]);

    expect(fn () => $this->service->run($user, ['dataset' => 'ai_usage']))->toThrow(AuthorizationException::class);
});

test('rejects anything outside the catalog', function (array $params) {
    expect(fn () => $this->service->run(aiDataUser(AI_DATA_FULL), ['dataset' => 'ai_usage', ...$params]))
        ->toThrow(ValidationException::class);
})->with([
    'unknown parameter' => [['columns' => ['email']]],
    'unknown dimension' => [['group_by' => ['email']]],
    'unknown metric' => [['metrics' => ['salary']]],
    'unknown filter' => [['filters' => ['npwp' => '123']]],
    'bad month' => [['from' => 'bulan lalu']],
    'end before start' => [['from' => '2026-05', 'to' => '2026-04']],
    'period too long' => [['from' => '2020-01', 'to' => '2026-05']],
    'limit too high' => [['limit' => 100000]],
    'compare with month grouping' => [['group_by' => ['month'], 'compare_previous' => true]],
]);

test('rejects an unknown dataset', function () {
    expect(fn () => $this->service->run(aiDataUser(AI_DATA_FULL), ['dataset' => 'users']))
        ->toThrow(ValidationException::class);
});

test('never returns personal data', function () {
    config(['ai_datasets.min_group_size' => 1]);
    $people = aiUsage($this->finance, 2, 1, '2026-05-10 09:00:00');

    $json = json_encode($this->service->run(aiDataUser(AI_DATA_FULL), [
        'dataset' => 'ai_usage', 'group_by' => ['department', 'month'], 'from' => '2026-05',
    ]));

    foreach ($people as $person) {
        expect($json)->not->toContain($person->name)->not->toContain($person->email);
    }
});

test('truncates to the requested limit and writes an audit log entry', function () {
    config(['ai_datasets.min_group_size' => 1]);
    aiUsage($this->finance, 1, 1, '2026-05-10 09:00:00');
    aiUsage($this->tech, 1, 1, '2026-05-10 09:00:00');
    $user = aiDataUser(AI_DATA_FULL);

    $result = $this->service->run($user, ['dataset' => 'ai_usage', 'group_by' => ['department'], 'from' => '2026-05', 'limit' => 1]);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['truncated'])->toBeTrue();

    $log = AiDataQueryLog::where('user_id', $user->id)->sole();

    expect($log->outcome)->toBe('ok')
        ->and($log->dataset)->toBe('ai_usage')
        ->and($log->row_count)->toBe(1)
        ->and($log->parameters['group_by'])->toBe(['department']);
});

test('legacy grants never hand out AI data permissions', function () {
    ResourcePermissions::ensureExist();
    $role = Role::findOrCreate('AI User');
    $role->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    ResourcePermissions::grantLegacyAccess();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['manage-ai-center']);
});
