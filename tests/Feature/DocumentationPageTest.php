<?php

use App\Models\Department;
use App\Models\User;
use App\Services\Sso\SsoUserProfile;
use App\Support\ApiPlaygroundPresets;
use App\Support\SsoPlaygroundSamples;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('renders the API documentation page publicly without authentication', function () {
    $response = $this->get('/tech/documentation');

    $response->assertOk()
        ->assertSee('Dokumentasi Teknis Nakama')
        ->assertSee('Cari di dokumentasi')
        ->assertSee('OPD Lookup API')
        ->assertSee('AI Data API')
        ->assertSee('/api/provinces')
        ->assertSee('/api/regencies')
        ->assertSee('/api/opds')
        ->assertSee('/api/opds/search')
        ->assertSee('per_page')
        ->assertSee('/api/ai/datasets')
        ->assertSee('/api/ai/query')
        ->assertSee('X-AI-User-Assertion')
        ->assertSee('from_date');
});

test('the old /documentation URL permanently redirects to /tech/documentation', function () {
    $this->get('/documentation')->assertMovedPermanently()->assertRedirect('/tech/documentation');
});

test('the Technology menu links to the API documentation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-documentation.view'));

    $this->actingAs($user)->get('/tech/documentation')
        ->assertOk()
        ->assertSee('<span class="grow">API Documentation</span>', false)
        ->assertSee(route('tech.documentation'), false);
});

test('the Technology links sit inside a collapsible submenu that is open on Technology pages, each with an icon', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-documentation.view'));

    $html = $this->actingAs($user)->get('/tech/documentation')->assertOk()->getContent();

    expect($html)
        ->toMatch('/aria-label="Technology menu toggle" class="peer" type="checkbox"\s+checked>/')
        ->toMatch('/<svg[^>]*>.*?<\/svg>\s*<span class="grow">API Documentation<\/span>/s');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('checked>', false)
        ->assertSee('Technology menu toggle', false);
});

test('users without the Technology menu do not get the link', function () {
    $this->actingAs(User::factory()->create())->get('/tech/documentation')
        ->assertOk()
        ->assertDontSee('<span class="grow">API Documentation</span>', false);
});

test('users with Technology access but not the documentation permission do not get the link', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'));

    $this->actingAs($user)->get('/tech/documentation')
        ->assertOk()
        ->assertDontSee('<span class="grow">API Documentation</span>', false);
});

test('the page is split into vertical tabs with only the first section shown before Alpine starts', function () {
    $html = $this->get('/tech/documentation')->assertOk()->getContent();

    expect($html)
        ->toContain('role="tablist"')
        ->toContain('id="overview" role="tabpanel" x-show="active === \'overview\'" class=')
        ->toContain('id="ai-auth" role="tabpanel" x-show="active === \'ai-auth\'" style="display: none"')
        ->toContain('href="#ai-auth" role="tab"')
        ->toContain('aria-selected:bg-primary ');

    // Every tab in the menu has a matching section.
    preg_match_all('/href="#([a-z-]+)" role="tab"/', $html, $tabs);
    preg_match_all('/id="([a-z-]+)" role="tabpanel"/', $html, $panels);

    expect($tabs[1])->toBe($panels[1])->toHaveCount(25);
});

test('the playground tab ships the ready-made commands to the page', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSee('href="#playground" role="tab"', false)
        ->assertSee('Coba API (Playground)')
        ->assertSee('Jalankan semua')
        ->assertSee('+ Tambah command');
});

test('the playground opens with the Jenis → Region → Provinsi → Kabupaten → OPD simulation and how to build it', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSeeInOrder(['Coba API (Playground)', 'Simulasi dropdown Jenis', 'Cara pakai di aplikasi Anda', 'Command siap pakai'])
        ->assertSee("this.call('jenis', '/api/opd-types')", false)
        ->assertSee("this.call('opd', '/api/opds?jenis=' + encodeURIComponent(jenis))", false)
        ->assertSee("this.call('region', '/api/regions?jenis=' + encodeURIComponent(jenis))", false)
        ->assertSee("'/api/opds?province_id=' + this.provinceId + this.jenisParam", false)
        ->assertSee('&opd_lv=1', false)
        ->assertSee('fill(opdSelect, await get(`/opds?province_id=${provinceSelect.value}${jenis()}`));', false);
});

test('the playground no longer lists validation-error commands', function () {
    expect(collect(ApiPlaygroundPresets::all())->pluck('expect')->unique()->all())->toBe([200]);
});

test('the SSO section explains the flow and the client setup with placeholders', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSee('href="#sso-laravel" role="tab"', false)
        ->assertSee('Login with SmartID')
        ->assertSee('SMARTID_CLIENT_ID=CLIENT_ID_DARI_SMARTID')
        ->assertSee('class SmartIdController extends Controller')
        ->assertSee('PanelsRenderHook::AUTH_LOGIN_FORM_AFTER')
        ->assertSee('Troubleshooting SSO');
});

test('every playground command returns the status it promises', function () {
    $presets = ApiPlaygroundPresets::all();

    expect($presets)->not->toBeEmpty();

    $this->withHeader('X-API-Key', publicApiToken());

    foreach ($presets as $preset) {
        expect($this->getJson($preset['path'])->status())
            ->toBe($preset['expect'], "{$preset['group']} › {$preset['label']} ({$preset['path']})");
    }
});

test('the SSO playground shows made-up data only, never a real employee', function () {
    $employee = User::factory()->create(['email' => 'real.employee@smartid.co.id']);

    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSee('href="#sso-playground" role="tab"', false)
        ->assertSee('budi@contoh.co.id')
        ->assertDontSee($employee->email);
});

test('the SSO playground samples have exactly the shape of the real API', function () {
    $samples = SsoPlaygroundSamples::all();
    $department = Department::query()->create(['name' => 'PMO']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::findOrCreate('Viewer'));

    expect($samples['me'])->toHaveCount(8)
        ->and(json_decode($samples['denied'], true))->toBe(['message' => 'Akun ini tidak memiliki akses ke aplikasi ini.']);

    foreach ($samples['me'] as $key => $json) {
        $scopes = ['profile', ...array_filter(explode(',', $key))];
        $real = app(SsoUserProfile::class)->toArray($user->fresh(SsoUserProfile::RELATIONS), $scopes);

        expect(array_keys(json_decode($json, true)))->toBe(array_keys($real), "scopes: {$key}")
            ->and(array_keys(json_decode($samples['members'][$key], true)['data'][0]))->toBe(array_keys($real));
    }
});

test('the OPD Lookup API documents the per-consumer API key', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSee('href="#api-key" role="tab"', false)
        ->assertSee('Autentikasi (API Key)')
        ->assertSee("'X-API-Key': 'smid_xxx'", false)
        ->assertSee('playground-api-key', false);
});
