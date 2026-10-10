<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use App\Models\User;
use App\Services\AiData\DataQueryService;
use App\Support\ResourcePermissions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function foDataUser(array $permissions, ?Department $department = null): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create(['department_id' => $department?->id]);
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), $permissions));

    return $user;
}

function fundingOfficer(string $name, Department $department, array $attributes = []): Employee
{
    $user = User::factory()->create(['name' => $name, 'department_id' => $department->id]);

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id, ...$attributes]);
}

function closedDeal(Employee $officer, string $closedOn, float $amount): FoFunnel
{
    return FoFunnel::factory()->create([
        'employee_id' => $officer->id,
        'stage' => 'closing',
        'tgl_leads' => $closedOn,
        'tgl_closing' => $closedOn,
        'nominal_investasi' => $amount,
    ]);
}

beforeEach(function () {
    $this->service = app(DataQueryService::class);
    $this->finance = Department::create(['name' => 'Departemen Finance']);
    $this->allAccess = ['manage-ai-center', 'ai-data-fo-achievement.view', 'ai-data-all-departments.view'];
});

test('ranks funding officers by closing value over all time, with achievement against saved targets', function () {
    $budi = fundingOfficer('Budi Santoso', $this->finance);
    $ani = fundingOfficer('Ani Wijaya', $this->finance);
    closedDeal($budi, '2025-03-10', 300_000_000);
    closedDeal($budi, '2026-02-10', 200_000_000);
    closedDeal($ani, '2026-05-10', 400_000_000);
    FoTarget::create(['employee_id' => $ani->id, 'year' => 2026, 'metric_key' => 'nilai_closing', 'yearly_target' => 1_200_000_000]);
    $this->travelTo('2026-06-15');

    $result = $this->service->run(foDataUser($this->allAccess), [
        'dataset' => 'fo_achievement',
        'group_by' => ['employee'],
        'metrics' => ['nilai_closing', 'closing', 'target_nilai_closing', 'achievement_pct'],
        'all_time' => true,
        'sort' => 'nilai_closing',
    ]);

    expect($result['period'])->toBe(['from' => '2025-03', 'to' => '2026-06'])
        ->and($result['suppressed_rows'])->toBe(0)
        ->and($result['rows'])->toBe([
            ['employee' => 'Budi Santoso', 'closing' => 2, 'nilai_closing' => 500_000_000.0, 'target_nilai_closing' => 0.0, 'achievement_pct' => null],
            // Ani: 2026 target 1.2B spread over 12 months; Jan-Jun 2026 = 600M; 400M / 600M = 66.7%.
            ['employee' => 'Ani Wijaya', 'closing' => 1, 'nilai_closing' => 400_000_000.0, 'target_nilai_closing' => 600_000_000.0, 'achievement_pct' => 66.7],
        ]);
});

test('counts funnel stages the same way as the FO Management page', function () {
    $officer = fundingOfficer('Citra', $this->finance);
    FoFunnel::factory()->create(['employee_id' => $officer->id, 'stage' => 'ql', 'tgl_leads' => '2026-05-02', 'tgl_qualified' => '2026-05-03']);
    $pitched = FoFunnel::factory()->create(['employee_id' => $officer->id, 'stage' => 'won', 'tgl_leads' => '2026-05-04', 'tgl_won' => '2026-05-20']);
    FoFunnelActivity::factory()->count(2)->create(['fo_funnel_id' => $pitched->id, 'stage' => 'pitching', 'tanggal' => '2026-05-10']);
    closedDeal($officer, '2026-05-25', 50_000_000);
    FoFunnel::factory()->create(['employee_id' => $officer->id, 'stage' => 'leads', 'tgl_leads' => '2026-05-05'])->delete();

    $row = $this->service->run(foDataUser($this->allAccess), [
        'dataset' => 'fo_achievement', 'group_by' => ['employee'], 'from' => '2026-05',
    ])['rows'][0];

    expect($row)->toMatchArray([
        'employee' => 'Citra',
        'leads' => 3,
        'ql' => 1,
        'pitching' => 1,
        'won' => 1,
        'closing' => 1,
        'nilai_closing' => 50_000_000.0,
    ]);
});

test('compares closing value with the previous period', function () {
    $officer = fundingOfficer('Dodi', $this->finance);
    closedDeal($officer, '2026-04-10', 100_000_000);
    closedDeal($officer, '2026-05-10', 150_000_000);

    $row = $this->service->run(foDataUser($this->allAccess), [
        'dataset' => 'fo_achievement', 'group_by' => ['employee'], 'metrics' => ['nilai_closing'], 'from' => '2026-05', 'compare_previous' => true,
    ])['rows'][0];

    expect($row)->toBe([
        'employee' => 'Dodi',
        'nilai_closing' => 150_000_000.0,
        'nilai_closing_previous' => 100_000_000.0,
        'nilai_closing_delta' => 50_000_000.0,
        'nilai_closing_delta_pct' => 50.0,
    ]);
});

test('sorting happens before the row limit so the top performer is kept', function () {
    foreach (['A' => 10, 'B' => 30, 'C' => 20] as $name => $millions) {
        closedDeal(fundingOfficer($name, $this->finance), '2026-05-10', $millions * 1_000_000);
    }

    $result = $this->service->run(foDataUser($this->allAccess), [
        'dataset' => 'fo_achievement', 'group_by' => ['employee'], 'metrics' => ['nilai_closing'], 'from' => '2026-05', 'sort' => 'nilai_closing', 'limit' => 1,
    ]);

    expect($result['rows'])->toBe([['employee' => 'B', 'nilai_closing' => 30_000_000.0]])
        ->and($result['truncated'])->toBeTrue();
});

test('never exposes sensitive employee data', function () {
    $officer = fundingOfficer('Eka', $this->finance, ['nik' => '3171234567890001', 'npwp' => '01.234.567.8-901.000', 'marital_status' => 'Menikah', 'employment_status' => 'PKWT']);
    closedDeal($officer, '2026-05-10', 1_000_000);

    $json = json_encode($this->service->run(foDataUser($this->allAccess), [
        'dataset' => 'fo_achievement', 'group_by' => ['employee', 'department', 'month'], 'from' => '2026-05',
    ]));

    expect($json)->toContain('Eka')
        ->not->toContain('3171234567890001')
        ->not->toContain('01.234.567.8-901.000')
        ->not->toContain('Menikah')
        ->not->toContain('PKWT');
});

test('a user without the all-departments permission only sees officers of their own department', function () {
    $other = Department::create(['name' => 'Departemen Lain']);
    closedDeal(fundingOfficer('Fajar', $this->finance), '2026-05-10', 1_000_000);
    closedDeal(fundingOfficer('Gita', $other), '2026-05-10', 1_000_000);

    $result = $this->service->run(foDataUser(['manage-ai-center', 'ai-data-fo-achievement.view'], $this->finance), [
        'dataset' => 'fo_achievement', 'group_by' => ['employee'], 'from' => '2026-05',
    ]);

    expect(collect($result['rows'])->pluck('employee')->all())->toBe(['Fajar']);
});

test('rejects invalid ranking and period combinations', function (array $params) {
    expect(fn () => $this->service->run(foDataUser($this->allAccess), ['dataset' => 'fo_achievement', ...$params]))
        ->toThrow(ValidationException::class);
})->with([
    'unknown sort column' => [['sort' => 'gaji']],
    'sort on a comparison column without comparing' => [['sort' => 'nilai_closing_delta']],
    'bad direction' => [['sort' => 'leads', 'direction' => 'sideways']],
    'all time with from' => [['all_time' => true, 'from' => '2026-01']],
    'all time with comparison' => [['all_time' => true, 'compare_previous' => true]],
    'nik as dimension' => [['group_by' => ['nik']]],
]);
