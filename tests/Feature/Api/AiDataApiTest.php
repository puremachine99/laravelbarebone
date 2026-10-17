<?php

use App\Enums\UserStatus;
use App\Models\AiConversation;
use App\Models\AiDataQueryLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\User;
use App\Services\AiData\UserAssertion;
use App\Support\ResourcePermissions;
use Spatie\Permission\Models\Permission;

function apiDataUser(array $permissions, array $attributes = []): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create($attributes);
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), ['manage-ai-center', ...$permissions]));

    return $user;
}

/**
 * @return array<string, string>
 */
function aiDataHeaders(User $user, ?int $conversationId = null): array
{
    $conversationId ??= AiConversation::factory()->create(['user_id' => $user->id])->id;

    return [
        'Authorization' => 'Bearer nkm_service-token',
        'X-AI-User-Assertion' => (string) app(UserAssertion::class)->issue($user, $conversationId),
    ];
}

beforeEach(function () {
    config([
        'ai_center.data_api.token' => 'nkm_service-token',
        'ai_center.data_api.assertion_secret' => 'assertion-secret',
    ]);

    $finance = Department::create(['name' => 'Departemen Finance']);
    $officer = Employee::create([
        'user_id' => User::factory()->create(['name' => 'Budi FO', 'department_id' => $finance->id])->id,
        'department_id' => $finance->id,
        'npwp' => '01.234.567.8-901.000',
    ]);
    FoFunnel::factory()->create(['employee_id' => $officer->id, 'stage' => 'closing', 'tgl_leads' => '2026-05-02', 'tgl_closing' => '2026-05-20', 'nominal_investasi' => 90_000_000]);

    $this->fullAccess = ['ai-data-fo-achievement.view', 'ai-data-all-departments.view'];
});

test('lists only the tools the asserted user may use, with skill and department', function () {
    $user = apiDataUser(['ai-data-fo-achievement.view']);

    $this->withHeaders(aiDataHeaders($user))->getJson('/api/ai/datasets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dataset', 'fo_achievement')
        ->assertJsonPath('data.0.skill', 'funding')
        ->assertJsonPath('data.0.department', 'Departemen Finance');
});

test('runs a query as the asserted user and logs it', function () {
    $user = apiDataUser($this->fullAccess);

    $this->withHeaders(aiDataHeaders($user))->postJson('/api/ai/query', [
        'dataset' => 'fo_achievement', 'group_by' => ['employee'], 'metrics' => ['nilai_closing'], 'from' => '2026-05',
    ])
        ->assertOk()
        ->assertJsonPath('rows.0.employee', 'Budi FO')
        ->assertJsonPath('rows.0.nilai_closing', 90_000_000)
        ->assertDontSee('01.234.567.8-901.000');

    expect(AiDataQueryLog::where('user_id', $user->id)->sole()->outcome)->toBe('ok');
});

test('the bearer token is required', function (array $headers) {
    $user = apiDataUser($this->fullAccess);
    $assertion = aiDataHeaders($user)['X-AI-User-Assertion'];

    $this->withHeaders([...$headers, 'X-AI-User-Assertion' => $assertion])->getJson('/api/ai/datasets')->assertUnauthorized();
})->with([
    'missing' => [[]],
    'wrong' => [['Authorization' => 'Bearer nkm_wrong']],
]);

test('a valid token without a valid assertion is not enough', function (string $assertion) {
    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token', 'X-AI-User-Assertion' => $assertion])
        ->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])
        ->assertUnauthorized();

    expect(AiDataQueryLog::count())->toBe(0);
})->with(['', 'not-an-assertion', 'eyJ1aWQiOjF9.forged-signature']);

test('an expired assertion is rejected', function () {
    $headers = aiDataHeaders(apiDataUser($this->fullAccess));

    $this->travel(10)->minutes();

    $this->withHeaders($headers)->getJson('/api/ai/datasets')->assertUnauthorized();
});

test('the user behind the assertion must still be active and existing', function (Closure $change) {
    $user = apiDataUser($this->fullAccess);
    $headers = aiDataHeaders($user);

    $change($user);

    $this->withHeaders($headers)->getJson('/api/ai/datasets')->assertForbidden();
})->with([
    'suspended' => [fn (User $user) => $user->update(['status' => UserStatus::Suspended])],
    'deleted' => [fn (User $user) => $user->delete()],
]);

test('the conversation in the assertion must belong to the user', function () {
    $user = apiDataUser($this->fullAccess);
    $someoneElsesConversation = AiConversation::factory()->create();

    $this->withHeaders(aiDataHeaders($user, $someoneElsesConversation->id))->getJson('/api/ai/datasets')->assertForbidden();
});

test('a dataset the user may not use is forbidden and logged', function () {
    $user = apiDataUser(['ai-data-ai-usage.view']);

    $this->withHeaders(aiDataHeaders($user))->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Anda tidak punya akses ke data ini.');

    expect(AiDataQueryLog::where('user_id', $user->id)->sole()->outcome)->toBe('denied');
});

test('the body cannot pick another user or ask for anything outside the catalog', function (array $body, string $error) {
    $this->withHeaders(aiDataHeaders(apiDataUser($this->fullAccess)))
        ->postJson('/api/ai/query', ['dataset' => 'fo_achievement', ...$body])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'user id in body' => [['user_id' => 1], 'params'],
    'sensitive dimension' => [['group_by' => ['nik']], 'group_by.0'],
    'sensitive filter' => [['filters' => ['npwp' => 'x']], 'filters'],
]);

test('queries are rate limited per user', function () {
    config(['ai_center.data.queries_per_hour' => 2]);
    $headers = aiDataHeaders(apiDataUser($this->fullAccess));

    $this->withHeaders($headers)->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertOk();
    $this->withHeaders($headers)->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertOk();
    $this->withHeaders($headers)->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertTooManyRequests();
});

test('the API is closed while the token or the assertion secret is not configured', function (string $setting) {
    $headers = aiDataHeaders(apiDataUser($this->fullAccess));

    config([$setting => null]);

    $this->withHeaders($headers)->getJson('/api/ai/datasets')->assertUnauthorized();
})->with(['ai_center.data_api.token', 'ai_center.data_api.assertion_secret']);

test('without a service account configured a request without assertion is rejected', function () {
    apiDataUser($this->fullAccess, ['email' => 'ai-service@example.com']);

    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token'])->getJson('/api/ai/datasets')->assertUnauthorized();
});

test('a request without assertion runs as the service account with its own permissions', function () {
    config(['ai_center.data_api.service_user' => 'ai-service@example.com']);
    $service = apiDataUser(['ai-data-fo-achievement.view'], ['email' => 'ai-service@example.com']);

    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token'])->getJson('/api/ai/datasets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dataset', 'fo_achievement');

    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token'])->postJson('/api/ai/query', ['dataset' => 'ai_usage'])
        ->assertForbidden();

    expect(AiDataQueryLog::where('user_id', $service->id)->sole()->outcome)->toBe('denied');
});

test('the service account still needs the bearer token', function () {
    config(['ai_center.data_api.service_user' => 'ai-service@example.com']);
    apiDataUser($this->fullAccess, ['email' => 'ai-service@example.com']);

    $this->getJson('/api/ai/datasets')->assertUnauthorized();
    $this->withHeaders(['Authorization' => 'Bearer nkm_wrong'])->getJson('/api/ai/datasets')->assertUnauthorized();
});

test('an invalid assertion does not fall back to the service account', function () {
    config(['ai_center.data_api.service_user' => 'ai-service@example.com']);
    apiDataUser($this->fullAccess, ['email' => 'ai-service@example.com']);

    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token', 'X-AI-User-Assertion' => 'eyJ1aWQiOjF9.forged'])
        ->getJson('/api/ai/datasets')
        ->assertUnauthorized();
});

test('a missing or inactive service account is forbidden', function (Closure $setup) {
    config(['ai_center.data_api.service_user' => 'ai-service@example.com']);
    $setup();

    $this->withHeaders(['Authorization' => 'Bearer nkm_service-token'])->getJson('/api/ai/datasets')->assertForbidden();
})->with([
    'missing' => [fn () => null],
    'suspended' => [fn () => apiDataUser([], ['email' => 'ai-service@example.com', 'status' => UserStatus::Suspended])],
]);

test('the service account has its own hourly query limit', function () {
    config(['ai_center.data_api.service_user' => 'ai-service@example.com', 'ai_center.data.queries_per_hour' => 1, 'ai_center.data_api.service_queries_per_hour' => 3]);
    apiDataUser($this->fullAccess, ['email' => 'ai-service@example.com']);
    $headers = ['Authorization' => 'Bearer nkm_service-token'];

    foreach (range(1, 3) as $attempt) {
        $this->withHeaders($headers)->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertOk();
    }

    $this->withHeaders($headers)->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertTooManyRequests();
});

test('rejected requests do not use up the query limit of real users', function () {
    config(['ai_center.data.queries_per_hour' => 1]);

    $this->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertUnauthorized();
    $this->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertUnauthorized();

    $this->withHeaders(aiDataHeaders(apiDataUser($this->fullAccess)))->postJson('/api/ai/query', ['dataset' => 'fo_achievement'])->assertOk();
});
