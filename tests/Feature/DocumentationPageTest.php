<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('renders the API documentation page publicly without authentication', function () {
    $response = $this->get('/documentation');

    $response->assertOk()
        ->assertSee('OPD Lookup API Documentation')
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
