<?php

use App\Models\FoFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('backfills tgl_won for legacy won funnels missing the date', function () {
    $legacyWon = FoFunnel::factory()->create([
        'stage' => 'won',
        'tgl_won' => null,
        'updated_at' => '2026-07-31 10:00:00',
    ]);

    $freshWon = FoFunnel::factory()->create([
        'stage' => 'won',
        'tgl_won' => '2026-08-18',
    ]);

    $this->artisan('app:sync-fo-funnel-stage-dates')->assertSuccessful();

    expect($legacyWon->refresh()->tgl_won->toDateString())->toBe('2026-07-31');
    expect($freshWon->refresh()->tgl_won->toDateString())->toBe('2026-08-18');
});

test('backfills tgl_closing for legacy closing/akad funnels missing the date', function () {
    $legacyClosing = FoFunnel::factory()->create([
        'stage' => 'closing',
        'tgl_closing' => null,
        'updated_at' => '2026-06-15 09:00:00',
    ]);

    $legacyAkad = FoFunnel::factory()->create([
        'stage' => 'akad',
        'tgl_closing' => null,
        'updated_at' => '2026-06-20 09:00:00',
    ]);

    $this->artisan('app:sync-fo-funnel-stage-dates')->assertSuccessful();

    expect($legacyClosing->refresh()->tgl_closing->toDateString())->toBe('2026-06-15');
    expect($legacyAkad->refresh()->tgl_closing->toDateString())->toBe('2026-06-20');
});

test('does not touch funnels that are not won/closing/akad', function () {
    $lead = FoFunnel::factory()->create([
        'stage' => 'leads',
        'tgl_won' => null,
        'tgl_closing' => null,
    ]);

    $this->artisan('app:sync-fo-funnel-stage-dates')->assertSuccessful();

    expect($lead->refresh()->tgl_won)->toBeNull();
    expect($lead->refresh()->tgl_closing)->toBeNull();
});
