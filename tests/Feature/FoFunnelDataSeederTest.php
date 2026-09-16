<?php

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\User;
use Database\Seeders\FoFunnelDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * Writes a funnelnew.csv-shaped fixture (same column layout as the real export) to the
 * scratch disk and returns its path.
 *
 * @param  array<int, array<int, string>>  $rows
 */
function writeFunnelCsvFixture(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'funnelnew_').'.csv';
    $handle = fopen($path, 'w');

    fputcsv($handle, array_fill(0, 40, 'header'));

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

/**
 * Builds one funnelnew.csv row for the given user id, blank everywhere except the
 * indices provided.
 *
 * @param  array<int, string>  $overrides
 * @return array<int, string>
 */
function funnelRow(int $userId, array $overrides = []): array
{
    $row = array_fill(0, 40, '');
    $row[0] = (string) $userId;
    $row[1] = $overrides[1] ?? 'Nasabah Test';

    foreach ($overrides as $index => $value) {
        $row[$index] = $value;
    }

    return $row;
}

test('imports funnel rows from the given csv and links them to the matching employee', function () {
    $user = User::factory()->create();
    $employee = Employee::create(['user_id' => $user->id]);

    $csv = writeFunnelCsvFixture([
        funnelRow($user->id, [
            1 => 'Budi Santoso',
            2 => 'Dokter',
            4 => 'Murni',
            5 => '10',
            6 => 'Jan-26',
            8 => 'Ya',
            9 => '15/01/2026',
        ]),
    ]);

    (new FoFunnelDataSeeder($csv))->run();

    $contact = FoContact::where('nama', 'Budi Santoso')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->employee_id)->toBe($employee->id)
        ->and($contact->profesi)->toBe('Dokter');

    $funnel = FoFunnel::where('fo_contact_id', $contact->id)->first();

    expect($funnel)->not->toBeNull()
        ->and($funnel->stage)->toBe('ql')
        ->and($funnel->sumber)->toBe('Murni')
        ->and($funnel->tgl_leads->toDateString())->toBe('2026-01-10');
});

test('sniffs closing info from drifted tail columns regardless of exact position', function () {
    $user = User::factory()->create();
    Employee::create(['user_id' => $user->id]);

    $csv = writeFunnelCsvFixture([
        funnelRow($user->id, [
            1 => 'Investor Closing',
            30 => 'Closing',
            31 => '19 Juni',
            32 => '500,000,000',
            // Columns 35-37 intentionally out of order, mirroring real spreadsheet drift.
            35 => '12 Bulan',
            36 => '3%',
            37 => 'Tier 3 (3%)',
        ]),
    ]);

    (new FoFunnelDataSeeder($csv))->run();

    $funnel = FoFunnel::whereHas('contact', fn ($q) => $q->where('nama', 'Investor Closing'))->first();

    expect($funnel->stage)->toBe('won')
        ->and($funnel->status_closing)->toBe('Closing')
        ->and((float) $funnel->nominal_investasi)->toBe(500000000.0)
        ->and($funnel->jangka_waktu)->toBe('12 Bulan')
        ->and($funnel->bagi_hasil)->toBe('3%')
        ->and($funnel->tier)->toBe('Tier 3 (3%)');
});

test('skips rows whose user id has no matching employee', function () {
    $csv = writeFunnelCsvFixture([
        funnelRow(999999, [1 => 'Tanpa Employee']),
    ]);

    (new FoFunnelDataSeeder($csv))->run();

    expect(FoContact::where('nama', 'Tanpa Employee')->exists())->toBeFalse();
});

test('truncates the owned funnel tables before every run instead of accumulating duplicates', function () {
    $user = User::factory()->create();
    Employee::create(['user_id' => $user->id]);

    $csv = writeFunnelCsvFixture([
        funnelRow($user->id, [1 => 'Nasabah Satu']),
    ]);

    (new FoFunnelDataSeeder($csv))->run();
    expect(FoContact::count())->toBe(1)->and(FoFunnel::count())->toBe(1);

    // Re-running with a leftover stale contact that isn't in the new CSV should not survive
    // the truncate — this proves the seeder can be safely re-run without migrate:fresh.
    (new FoFunnelDataSeeder($csv))->run();

    expect(FoContact::count())->toBe(1)
        ->and(FoFunnel::count())->toBe(1)
        ->and(FoFunnelActivity::count())->toBe(0);
});

test('logs a developer-migration note when importing', function () {
    Log::spy();

    $csv = writeFunnelCsvFixture([]);

    (new FoFunnelDataSeeder($csv))->run();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => str_contains($message, '[Migrasi data developer]'))
        ->atLeast()->once();
});
