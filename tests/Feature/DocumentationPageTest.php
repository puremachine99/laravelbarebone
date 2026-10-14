<?php

use App\Models\User;
use App\Support\ApiPlaygroundPresets;
use Spatie\Permission\Models\Permission;

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
        ->assertSee('title="API Documentation"', false)
        ->assertSee(route('tech.documentation'), false);
});

test('users without the Technology menu do not get the link', function () {
    $this->actingAs(User::factory()->create())->get('/tech/documentation')
        ->assertOk()
        ->assertDontSee('title="API Documentation"', false);
});

test('users with Technology access but not the documentation permission do not get the link', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'));

    $this->actingAs($user)->get('/tech/documentation')
        ->assertOk()
        ->assertDontSee('title="API Documentation"', false);
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

    expect($tabs[1])->toBe($panels[1])->toHaveCount(21);
});

test('the playground tab ships the ready-made commands to the page', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSee('href="#playground" role="tab"', false)
        ->assertSee('Coba API (Playground)')
        ->assertSee('Jalankan semua')
        ->assertSee('+ Tambah command');
});

test('the playground opens with the Region → Provinsi → Kabupaten → OPD simulation and how to build it', function () {
    $this->get('/tech/documentation')
        ->assertOk()
        ->assertSeeInOrder(['Coba API (Playground)', 'Simulasi dropdown Region', 'Cara pakai di aplikasi Anda', 'Command siap pakai'])
        ->assertSee("this.call('region', '/api/regions')", false)
        ->assertSee("'/api/opds?regency_id=' + regencyId", false)
        ->assertSee('fill(opdSelect, await get(`/opds?regency_id=${regencySelect.value}`));', false);
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

    foreach ($presets as $preset) {
        expect($this->getJson($preset['path'])->status())
            ->toBe($preset['expect'], "{$preset['group']} › {$preset['label']} ({$preset['path']})");
    }
});
