<?php

use App\Livewire\Tech\SsoClients;
use App\Models\Department;
use App\Models\OAuthClient;
use App\Models\OAuthClientAudit;
use App\Models\User;
use App\Services\Sso\SsoClientManager;
use App\Support\ResourcePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function ssoAdmin(): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-sso.view'));

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ssoClient(array $overrides = []): OAuthClient
{
    return app(SsoClientManager::class)->create([
        'name' => 'Procurement',
        'description' => null,
        'redirect_uris' => ['https://procurement.test/auth/smartid/callback'],
        'confidential' => true,
        'scopes' => ['profile', 'email'],
        'skip_authorization' => false,
        'allowed_roles' => [],
        'allowed_department_ids' => [],
        ...$overrides,
    ]);
}

function issueToken(OAuthClient $client, User $user, bool $revoked = false): string
{
    $id = bin2hex(random_bytes(20));

    DB::table('oauth_access_tokens')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'name' => null,
        'scopes' => json_encode(['profile']),
        'revoked' => $revoked,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    return $id;
}

test('guests are redirected to login', function () {
    $this->get(route('tech.sso'))->assertRedirect(route('login'));
});

test('users without the SSO permission are forbidden', function () {
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-ai-monitor.view'));

    $this->actingAs($user)->get(route('tech.sso'))->assertForbidden();
});

test('the page asks for the password first', function () {
    $this->actingAs(ssoAdmin())->get(route('tech.sso'))->assertRedirect(route('password.confirm'));
});

test('the page lists connected apps after the password is confirmed', function () {
    ssoClient(['name' => 'Nakama Procurement']);

    $this->actingAs(ssoAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('tech.sso'))
        ->assertOk()
        ->assertSee('SSO / Connected Apps')
        ->assertSee('Nakama Procurement')
        ->assertSee('title="SSO / Connected Apps"', false);
});

test('the component refuses users without the permission even when mounted directly', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SsoClients::class)->assertForbidden();
});

test('an admin registers a confidential app: Passport keeps a hash, the stored copy decrypts to the same secret', function () {
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->set('redirectUris', "https://procurement.test/auth/smartid/callback\n  http://localhost:8001/auth/smartid/callback ")
        ->set('scopes', ['profile', 'email', 'department'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showGuide', true);

    $client = OAuthClient::query()->sole();
    $hash = DB::table('oauth_clients')->value('secret');
    $plain = $client->storedSecret->secret;

    expect($client->redirect_uris)->toBe(['https://procurement.test/auth/smartid/callback', 'http://localhost:8001/auth/smartid/callback'])
        ->and($client->scopes)->toBe(['profile', 'email', 'department'])
        ->and($client->grant_types)->toBe(['authorization_code', 'refresh_token', 'client_credentials'])
        ->and($hash)->not->toBe($plain)
        ->and(Hash::check($plain, $hash))->toBeTrue()
        ->and(DB::table('oauth_client_secrets')->value('secret'))->not->toContain($plain)
        ->and(OAuthClientAudit::query()->pluck('action')->all())->toBe(['created', 'secret_revealed']);
});

test('a public (PKCE) app has no secret', function () {
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Mobile')
        ->set('clientType', 'public')
        ->set('redirectUris', 'https://mobile.test/callback')
        ->call('save')
        ->assertHasNoErrors();

    $client = OAuthClient::query()->sole();

    expect($client->confidential())->toBeFalse()
        ->and($client->storedSecret)->toBeNull()
        ->and($client->grant_types)->not->toContain('client_credentials');
});

test('plain http redirect URIs are rejected outside localhost', function (string $uri) {
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Insecure')
        ->set('redirectUris', $uri)
        ->call('save')
        ->assertHasErrors('redirectUris');

    expect(OAuthClient::query()->count())->toBe(0);
})->with([
    'http on a public host' => 'http://procurement.example.com/callback',
    'not a url' => 'procurement',
    'fragment' => 'https://procurement.example.com/callback#x',
]);

test('the secret can be revealed again with one click and every reveal is audited', function () {
    $client = ssoClient();
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('openGuide', $client->getKey())
        ->assertDontSee($client->storedSecret->secret)
        ->call('revealSecret', $client->getKey())
        ->assertSet('revealedSecret', $client->storedSecret->secret)
        ->assertSee($client->storedSecret->secret);

    expect(OAuthClientAudit::query()->where('action', 'secret_revealed')->count())->toBe(1);
});

test('revealing the secret needs a fresh password confirmation', function () {
    $client = ssoClient();
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1]);

    Livewire::test(SsoClients::class)
        ->call('revealSecret', $client->getKey())
        ->assertRedirect(route('password.confirm'))
        ->assertSet('revealedSecret', null);

    expect(OAuthClientAudit::query()->where('action', 'secret_revealed')->exists())->toBeFalse();
});

test('regenerating the secret invalidates the old one and updates the stored copy', function () {
    $client = ssoClient();
    $old = $client->storedSecret->secret;
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)->call('regenerateSecret', $client->getKey());

    $client->refresh();
    $new = $client->storedSecret->secret;
    $hash = DB::table('oauth_clients')->value('secret');

    expect($new)->not->toBe($old)
        ->and(Hash::check($new, $hash))->toBeTrue()
        ->and(Hash::check($old, $hash))->toBeFalse()
        ->and(OAuthClientAudit::query()->where('action', 'secret_regenerated')->exists())->toBeTrue();
});

test('settings can be edited', function () {
    Role::findOrCreate('Finance');
    $client = ssoClient();
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('edit', $client->getKey())
        ->assertSet('name', 'Procurement')
        ->assertSet('appUrl', 'https://procurement.test')
        ->assertSet('redirectUris', '')
        ->assertSet('restrictAccess', false)
        ->set('name', 'Procurement v2')
        ->set('skipAuthorization', true)
        ->set('restrictAccess', true)
        ->set('allowedRoles', ['Finance'])
        ->call('save')
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->name)->toBe('Procurement v2')
        ->and($client->redirect_uris)->toBe(['https://procurement.test/auth/smartid/callback'])
        ->and($client->skip_authorization)->toBeTrue()
        ->and($client->allowed_roles)->toBe(['Finance']);
});

test('the quick form only needs a name and the app URL; the callback is derived and the secret shown right away', function () {
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    $component = Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->set('appUrl', ' http://localhost:8000/ ')
        ->call('save')
        ->assertHasNoErrors();

    $client = OAuthClient::query()->sole();
    $secret = $client->storedSecret->secret;

    expect($client->redirect_uris)->toBe(['http://localhost:8000/auth/smartid/callback'])
        ->and($client->scopes)->toBe(['profile', 'email'])
        ->and($client->confidential())->toBeTrue()
        ->and($client->allowed_roles)->toBeNull()
        ->and($client->skip_authorization)->toBeFalse();

    $component->assertSet('showGuide', true)
        ->assertSet('revealedSecret', $secret)
        ->assertSee("SMARTID_CLIENT_SECRET={$secret}")
        ->assertSee("SMARTID_CLIENT_ID={$client->getKey()}")
        ->assertSee('SMARTID_REDIRECT_URI=http://localhost:8000/auth/smartid/callback')
        ->assertSee('class SmartIdController extends Controller');
});

test('the app URL is required unless manual redirect URIs are given', function () {
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->call('save')
        ->assertHasErrors(['appUrl' => 'required_without']);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->set('appUrl', 'http://procurement.example.com')
        ->call('save')
        ->assertHasErrors('appUrl');

    expect(OAuthClient::query()->count())->toBe(0);
});

test('restricting access needs at least one role or department, and turning it off clears the picks', function () {
    Role::findOrCreate('Finance');
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->set('appUrl', 'https://procurement.test')
        ->set('restrictAccess', true)
        ->call('save')
        ->assertHasErrors('allowedRoles');

    Livewire::test(SsoClients::class)
        ->call('create')
        ->set('name', 'Procurement')
        ->set('appUrl', 'https://procurement.test')
        ->set('allowedRoles', ['Finance'])
        ->set('restrictAccess', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(OAuthClient::query()->sole()->allowed_roles)->toBeNull();
});

test('revoking sessions signs users out of one app only', function () {
    $client = ssoClient();
    $other = ssoClient(['name' => 'Other']);
    $user = User::factory()->create();
    $mine = issueToken($client, $user);
    $theirs = issueToken($other, $user);
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)->call('revokeTokens', $client->getKey());

    expect(Passport::token()->find($mine)->revoked)->toBeTrue()
        ->and(Passport::token()->find($theirs)->revoked)->toBeFalse();
});

test('revoking one user leaves the other users signed in', function () {
    $client = ssoClient();
    [$alice, $bob] = User::factory()->count(2)->create();
    $aliceToken = issueToken($client, $alice);
    $bobToken = issueToken($client, $bob);
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->set('tab', 'sessions')
        ->assertSee($alice->email)
        ->call('revokeTokens', $client->getKey(), $alice->id);

    expect(Passport::token()->find($aliceToken)->revoked)->toBeTrue()
        ->and(Passport::token()->find($bobToken)->revoked)->toBeFalse();
});

test('disabling an app revokes its sessions and enabling brings it back', function () {
    $client = ssoClient();
    $token = issueToken($client, User::factory()->create());
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    $component = Livewire::test(SsoClients::class)->call('toggleEnabled', $client->getKey());

    expect($client->refresh()->revoked)->toBeTrue()
        ->and(Passport::token()->find($token)->revoked)->toBeTrue();

    $component->call('toggleEnabled', $client->getKey());

    expect($client->refresh()->revoked)->toBeFalse();
});

test('deleting an app removes it with its tokens and stored secret', function () {
    $client = ssoClient();
    issueToken($client, User::factory()->create());
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)->call('delete', $client->getKey());

    expect(OAuthClient::query()->count())->toBe(0)
        ->and(DB::table('oauth_access_tokens')->count())->toBe(0)
        ->and(DB::table('oauth_client_secrets')->count())->toBe(0)
        ->and(OAuthClientAudit::query()->where('action', 'deleted')->exists())->toBeTrue();
});

test('the simulation shows the /api/me response an app would get for a user', function () {
    $pmo = Department::query()->create(['name' => 'PMO']);
    $client = ssoClient(['scopes' => ['profile', 'email', 'department'], 'allowed_department_ids' => [$pmo->id]]);
    $allowed = User::factory()->create(['name' => 'Ani PMO', 'department_id' => $pmo->id]);
    $outsider = User::factory()->create(['name' => 'Budi Finance']);
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('openSimulation', $client->getKey())
        ->set('simulationSearch', 'Ani')
        ->assertSee('Ani PMO')
        ->assertDontSee('Budi Finance')
        ->call('simulateUser', $allowed->id)
        ->assertSee('✓ Boleh login')
        ->assertSee('HTTP 200')
        ->assertSee('"email": "'.$allowed->email.'"')
        ->assertSee('"name": "PMO"')
        ->call('simulateUser', $outsider->id)
        ->assertSee('✗ Ditolak')
        ->assertSee('HTTP 403')
        ->assertSee('Bukan role/departemen yang diizinkan')
        ->assertDontSee($outsider->email);
});

test('the simulation explains why a suspended user is refused', function () {
    $client = ssoClient();
    $suspended = User::factory()->suspended()->create();
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('openSimulation', $client->getKey())
        ->call('simulateUser', $suspended->id)
        ->assertSee('✗ Ditolak')
        ->assertSee('Akun berstatus suspended');
});

test('the sync simulation counts and previews the members the app would receive', function () {
    $pmo = Department::query()->create(['name' => 'PMO']);
    $client = ssoClient(['allowed_department_ids' => [$pmo->id]]);
    User::factory()->count(2)->create(['department_id' => $pmo->id]);
    User::factory()->suspended()->create(['department_id' => $pmo->id]);
    User::factory()->create();
    $this->actingAs(ssoAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(SsoClients::class)
        ->call('openSimulation', $client->getKey())
        ->assertSee('Jalankan simulasi sync')
        ->call('runSyncSimulation')
        ->assertSeeHtml('<strong class="tabular-nums">2</strong> user akan tersinkron');
});

test('the simulation is refused without the SSO permission', function () {
    $client = ssoClient();
    $this->actingAs(User::factory()->create());

    Livewire::test(SsoClients::class)->assertForbidden();
});
