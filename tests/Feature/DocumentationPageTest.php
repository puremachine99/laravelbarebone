<?php

test('renders the API documentation page publicly without authentication', function () {
    $response = $this->get('/documentation');

    $response->assertOk()
        ->assertSee('OPD Lookup API Documentation')
        ->assertSee('/api/provinces')
        ->assertSee('/api/regencies')
        ->assertSee('/api/opds')
        ->assertSee('/api/opds/search')
        ->assertSee('per_page');
});
