<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\FoFunnel;
use App\Models\FoTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function heatmapEmployee(): Employee
{
    $department = Department::firstOrCreate(['name' => 'Departemen Finance']);
    $user = User::factory()->create();

    return Employee::create(['user_id' => $user->id, 'department_id' => $department->id]);
}

test('shows an empty state when the employee has no FoTarget rows at all', function () {
    $viewer = User::factory()->create();
    $employee = heatmapEmployee();

    $this->actingAs($viewer);

    Livewire::test('kpi.employee-target-heatmap', ['employee' => $employee])
        ->assertSee('Belum ada target KPI Funding')
        ->assertDontSee('Bulan');
});

test('all targetable metrics show up as rows, each with their own daily counts and percent-to-target', function () {
    $viewer = User::factory()->create();
    $employee = heatmapEmployee();

    FoTarget::factory()->create([
        'employee_id' => $employee->id,
        'year' => now()->year,
        'metric_key' => 'leads',
        'yearly_target' => 300, // 300/12/25 = 1 lead/day target
    ]);

    $day5 = now()->startOfMonth()->addDays(4);

    FoFunnel::factory()->create(['employee_id' => $employee->id, 'tgl_leads' => $day5->toDateString()]);
    FoFunnel::factory()->create(['employee_id' => $employee->id, 'tgl_leads' => $day5->toDateString()]);

    $this->actingAs($viewer);

    $component = Livewire::test('kpi.employee-target-heatmap', ['employee' => $employee])
        ->assertSee('KPI Funding');

    $heatmap = $component->instance()->heatmap();
    $rows = collect($heatmap['rows'])->keyBy('key');

    expect($rows->keys()->all())->toBe(['leads', 'ql', 'pitching', 'closing'])
        ->and($rows['leads']['days'][5]['count'])->toBe(2)
        ->and($rows['leads']['days'][5]['percent'])->toBe(200.0)
        ->and($rows['leads']['total_realisasi'])->toBe(2)
        ->and($rows['ql']['total_realisasi'])->toBe(0);
});

test('switching month recomputes the heatmap for all metric rows', function () {
    $viewer = User::factory()->create();
    $employee = heatmapEmployee();

    FoTarget::factory()->create([
        'employee_id' => $employee->id,
        'year' => now()->year,
        'metric_key' => 'closing',
        'yearly_target' => 1200,
    ]);

    FoFunnel::factory()->create([
        'employee_id' => $employee->id,
        'stage' => 'closing',
        'tgl_closing' => now()->toDateString(),
    ]);

    $this->actingAs($viewer);

    $component = Livewire::test('kpi.employee-target-heatmap', ['employee' => $employee])
        ->set('selectedMonth', (int) now()->month)
        ->set('selectedYear', (int) now()->year);

    $heatmap = $component->instance()->heatmap();
    $closingRow = collect($heatmap['rows'])->firstWhere('key', 'closing');

    expect($closingRow['days'][(int) now()->day]['count'])->toBe(1);
});
