<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('renders the API documentation page publicly without authentication', function () {
    $response = $this->get('/documentation');

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

test('the Technology menu links to the API documentation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'));

    $this->actingAs($user)->get('/documentation')
        ->assertOk()
        ->assertSee('title="API Documentation"', false)
        ->assertSee(route('documentation'), false);
});

test('users without the Technology menu do not get the link', function () {
    $this->actingAs(User::factory()->create())->get('/documentation')
        ->assertOk()
        ->assertDontSee('title="API Documentation"', false);
});

test('the page is split into vertical tabs with only the first section shown before Alpine starts', function () {
    $html = $this->get('/documentation')->assertOk()->getContent();

    expect($html)
        ->toContain('role="tablist"')
        ->toContain('id="overview" role="tabpanel" x-show="active === \'overview\'" class=')
        ->toContain('id="ai-auth" role="tabpanel" x-show="active === \'ai-auth\'" style="display: none"')
        ->toContain('href="#ai-auth" role="tab"')
        ->toContain('aria-selected:bg-primary ');

    // Every tab in the menu has a matching section.
    preg_match_all('/href="#([a-z-]+)" role="tab"/', $html, $tabs);
    preg_match_all('/id="([a-z-]+)" role="tabpanel"/', $html, $panels);

    expect($tabs[1])->toBe($panels[1])->toHaveCount(15);
});
