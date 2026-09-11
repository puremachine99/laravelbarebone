<?php

test('renders the timeline calculator page publicly with feature-grouping and per-unit controls', function () {
    $response = $this->get('/tech');

    $response->assertOk()
        ->assertSee('Enterprise Timeline Calculator')
        ->assertSee('Shared / Dasar (dihitung sekali)')
        ->assertSee('Salin Ringkasan')
        ->assertSee('isPerUnitTask', false)
        ->assertSee('effectivePhase', false)
        ->assertSee('addFeature', false)
        ->assertSee('getFeatureSP', false)
        ->assertSee('copySummary', false)
        ->assertSee('targetDate', false);
});
