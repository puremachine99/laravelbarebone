<?php

use App\Models\Department;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Sso\SsoClientManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

const SSO_REDIRECT = 'https://procurement.test/auth/smartid/callback';

beforeEach(function () {
    // Real RSA keys, generated once per machine outside the repo, so the full flow (signed JWTs) can run.
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
function flowClient(array $overrides = []): OAuthClient
{
    return app(SsoClientManager::class)->create([
        'name' => 'Procurement',
        'description' => null,
        'redirect_uris' => [SSO_REDIRECT],
        'confidential' => true,
        'scopes' => ['profile', 'email'],
        'skip_authorization' => false,
        'allowed_roles' => [],
        'allowed_department_ids' => [],
        ...$overrides,
    ]);
}

/**
 * @param  array<string, string>  $extra
 */
function authorizeUrl(OAuthClient $client, string $scope = 'profile email', array $extra = []): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => SSO_REDIRECT,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'xyz',
        ...$extra,
    ]);
}

function codeFrom(TestResponse $response): string
{
    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith(SSO_REDIRECT)
        ->and($query['state'] ?? null)->toBe('xyz');

    return $query['code'];
}

/**
 * Approve the consent screen the way the browser form does and return the authorization code.
 */
function approve(OAuthClient $client): string
{
    return codeFrom(test()->post('/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $client->getKey(),
        'auth_token' => session('authToken'),
    ]));
}

/**
 * @return array<string, mixed>
 */
function exchange(OAuthClient $client, string $code, ?string $secret = null): array
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'client_secret' => $secret ?? $client->storedSecret->secret,
        'redirect_uri' => SSO_REDIRECT,
        'code' => $code,
    ])->assertOk()->json();
}

function me(string $accessToken): TestResponse
{
    // The web session from actingAs() must not leak into the bearer-token request.
    app('auth')->forgetGuards();

    return test()->withToken($accessToken)->getJson('/api/me');
}

test('guests are sent to the SmartID login page first', function () {
    $this->get(authorizeUrl(flowClient()))->assertRedirect(route('login'));
});

test('full login: consent screen, code exchange and /api/me with the granted scopes only', function () {
    $department = Department::query()->create(['name' => 'Finance']);
    $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@smartid.co.id', 'department_id' => $department->id]);
    $client = flowClient();

    $this->actingAs($user)
        ->get(authorizeUrl($client))
        ->assertOk()
        ->assertSee('Masuk ke Procurement')
        ->assertSee('Alamat email');

    $tokens = exchange($client, approve($client));

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token', 'expires_in']);

    me($tokens['access_token'])
        ->assertOk()
        ->assertJson(['id' => $user->id, 'name' => 'Budi', 'email' => 'budi@smartid.co.id'])
        ->assertJsonMissingPath('department')
        ->assertJsonMissingPath('roles');

    expect($client->refresh()->last_used_at)->not->toBeNull();
});

test('scopes the app is not allowed to use are dropped from the token', function () {
    Role::findOrCreate('Finance');
    $user = User::factory()->create();
    $user->assignRole('Finance');
    $client = flowClient(['scopes' => ['profile']]);

    $this->actingAs($user)->get(authorizeUrl($client, 'profile email roles'))->assertOk();
    $tokens = exchange($client, approve($client));

    me($tokens['access_token'])
        ->assertOk()
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('roles');
});

test('the roles and department scopes return roles, permissions and department', function () {
    Role::findOrCreate('Finance');
    $department = Department::query()->create(['name' => 'Finance']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole('Finance');
    $client = flowClient(['scopes' => ['profile', 'department', 'roles']]);

    $this->actingAs($user)->get(authorizeUrl($client, 'profile department roles'))->assertOk();
    $tokens = exchange($client, approve($client));

    me($tokens['access_token'])
        ->assertOk()
        ->assertJsonPath('department.name', 'Finance')
        ->assertJsonPath('roles', ['Finance']);
});

test('trusted apps skip the consent screen', function () {
    $client = flowClient(['skip_authorization' => true]);

    $code = codeFrom($this->actingAs(User::factory()->create())->get(authorizeUrl($client)));

    expect(exchange($client, $code))->toHaveKey('access_token');
});

test('a wrong client secret cannot exchange the code', function () {
    $client = flowClient(['skip_authorization' => true]);
    $code = codeFrom($this->actingAs(User::factory()->create())->get(authorizeUrl($client)));

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'client_secret' => 'wrong',
        'redirect_uri' => SSO_REDIRECT,
        'code' => $code,
    ])->assertUnauthorized();
});

test('an unregistered redirect URI is refused', function () {
    $client = flowClient();

    $this->actingAs(User::factory()->create())
        ->get(authorizeUrl($client, extra: ['redirect_uri' => 'https://evil.test/callback']))
        ->assertUnauthorized();
});

test('restricted apps reject users outside the allowed roles and departments', function () {
    Role::findOrCreate('Finance');
    $finance = Department::query()->create(['name' => 'Finance']);
    $client = flowClient(['allowed_roles' => ['Finance'], 'allowed_department_ids' => [$finance->id]]);

    $this->actingAs(User::factory()->create())
        ->get(authorizeUrl($client))
        ->assertForbidden()
        ->assertSee('Tidak bisa masuk ke Procurement');

    $byRole = User::factory()->create();
    $byRole->assignRole('Finance');
    $this->actingAs($byRole)->get(authorizeUrl($client))->assertOk();

    $byDepartment = User::factory()->create(['department_id' => $finance->id]);
    $this->actingAs($byDepartment)->get(authorizeUrl($client))->assertOk();
});

test('a user suspended after login can no longer read /api/me', function () {
    $user = User::factory()->create();
    $client = flowClient(['skip_authorization' => true]);
    $tokens = exchange($client, codeFrom($this->actingAs($user)->get(authorizeUrl($client))));

    $user->forceFill(['status' => 'suspended'])->save();

    me($tokens['access_token'])->assertForbidden();
});

test('tokens revoked from /tech/sso stop working', function () {
    $client = flowClient(['skip_authorization' => true]);
    $tokens = exchange($client, codeFrom($this->actingAs(User::factory()->create())->get(authorizeUrl($client))));

    app(SsoClientManager::class)->revokeTokens($client);

    me($tokens['access_token'])->assertUnauthorized();
});

test('a disabled app cannot start a login', function () {
    $client = flowClient();
    app(SsoClientManager::class)->setEnabled($client, false);

    $this->actingAs(User::factory()->create())->get(authorizeUrl($client))->assertUnauthorized();
});

test('/api/me requires a bearer token', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
