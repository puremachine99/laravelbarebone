<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OAuthClient;
use App\Models\Position;
use App\Models\User;
use App\Services\Sso\SsoClientManager;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Real RSA keys, generated once per machine outside the repo, so tokens can be issued (same as SsoLoginFlowTest).
    $path = sys_get_temp_dir().'/smartdashboard-passport-test-keys';

    if (! is_file($path.'/oauth-private.key')) {
        @mkdir($path, 0700, true);
        Passport::loadKeysFrom($path);
        Artisan::call('passport:keys', ['--force' => true, '--length' => 2048]);
    }

    Passport::loadKeysFrom($path);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function membersClient(array $overrides = []): OAuthClient
{
    return app(SsoClientManager::class)->create([
        'name' => 'PMO App',
        'description' => null,
        'redirect_uris' => ['https://pmo.test/auth/smartid/callback'],
        'confidential' => true,
        'scopes' => ['profile'],
        'skip_authorization' => true,
        'allowed_roles' => [],
        'allowed_department_ids' => [],
        ...$overrides,
    ]);
}

/**
 * The app's own (machine) token, as its daily sync would request it.
 */
function clientToken(OAuthClient $client): string
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->storedSecret->secret,
    ])->assertOk()->json('access_token');
}

test('an app lists only the active users it allows, with the fields of its scopes', function () {
    Role::findOrCreate('PMO Viewer');
    $pmo = Department::query()->create(['name' => 'PMO']);
    $finance = Department::query()->create(['name' => 'Finance']);
    $client = membersClient([
        'scopes' => ['profile', 'email', 'department'],
        'allowed_roles' => ['PMO Viewer'],
        'allowed_department_ids' => [$pmo->id],
    ]);

    $pmoStaff = User::factory()->create(['name' => 'Ani', 'department_id' => $pmo->id]);
    Employee::create([
        'user_id' => $pmoStaff->id,
        'department_id' => $pmo->id,
        'position_struktural_id' => Position::create(['name' => 'Manager of Partnership', 'type' => 'Struktural'])->id,
        'position_fungsional_id' => Position::create(['name' => 'Partnership Officer', 'type' => 'Fungsional'])->id,
    ]);
    $viaRole = User::factory()->create(['name' => 'Budi', 'department_id' => $finance->id]);
    $viaRole->assignRole('PMO Viewer');
    User::factory()->suspended()->create(['department_id' => $pmo->id]);
    User::factory()->create(['department_id' => $finance->id]);

    $response = $this->withToken(clientToken($client))->getJson('/api/sso/members')->assertOk();

    expect($response->json('total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('name')->all())->toBe(['Ani', 'Budi']);

    $response
        ->assertJsonPath('data.0.email', $pmoStaff->email)
        ->assertJsonPath('data.0.department.name', 'PMO')
        ->assertJsonPath('data.0.positions.struktural.name', 'Manager of Partnership')
        ->assertJsonPath('data.0.positions.fungsional.name', 'Partnership Officer')
        ->assertJsonPath('data.0.positions.teknis', null)
        ->assertJsonMissingPath('data.0.roles');
});

test('an unrestricted app lists every active user, with profile fields only', function () {
    User::factory()->count(2)->create();
    User::factory()->suspended()->create();
    $client = membersClient();

    $this->withToken(clientToken($client))->getJson('/api/sso/members')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.department');
});

test('the member list is paginated', function () {
    User::factory()->count(3)->create();
    $client = membersClient();

    $token = clientToken($client);

    $firstPage = $this->withToken($token)->getJson('/api/sso/members?per_page=2')->assertOk()->assertJsonCount(2, 'data');

    // The client follows next_page_url as-is, so it must keep per_page.
    expect($firstPage->json('next_page_url'))->toContain('per_page=2');

    $this->withToken($token)->getJson($firstPage->json('next_page_url'))
        ->assertOk()
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('current_page', 2)
        ->assertJsonPath('next_page_url', null)
        ->assertJsonCount(1, 'data');
});

test('a token issued on behalf of a user cannot read the member list', function () {
    $client = membersClient();
    $user = User::factory()->create();

    $location = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://pmo.test/auth/smartid/callback',
        'response_type' => 'code',
        'state' => 'xyz',
    ]))->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $userToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'client_secret' => $client->storedSecret->secret,
        'redirect_uri' => 'https://pmo.test/auth/smartid/callback',
        'code' => $query['code'],
    ])->json('access_token');

    app('auth')->forgetGuards();

    $this->withToken($userToken)->getJson('/api/sso/members')->assertUnauthorized();
});

test('the app token cannot be used as a user token on /api/me', function () {
    $this->withToken(clientToken(membersClient()))->getJson('/api/me')->assertUnauthorized();
});

test('a disabled app loses its member access', function () {
    $client = membersClient();
    $token = clientToken($client);

    app(SsoClientManager::class)->setEnabled($client, false);

    $this->withToken($token)->getJson('/api/sso/members')->assertUnauthorized();
});

test('the member list requires a token', function () {
    $this->getJson('/api/sso/members')->assertUnauthorized();
});
