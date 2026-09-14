<?php

namespace App\Services;

use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use Illuminate\Support\Carbon;

/**
 * Shared realisasi/target math for the Funding Officer KPI metrics (leads, ql, pitching,
 * followup, won, closing, nilai_closing) — used by both the FO Daily Dashboard's Activity
 * Tracker/Stat Cards and the per-employee KPI heatmap on profile pages, so the two never
 * drift apart on what counts as "realisasi" for a given metric.
 */
class FoTargetMetrics
{
    /**
     * Metrics that can actually have a yearly target configured on the FO Management page.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function targetableMetrics(): array
    {
        return [
            ['key' => 'leads', 'label' => 'Leads'],
            ['key' => 'ql', 'label' => 'Qualified Lead'],
            ['key' => 'pitching', 'label' => 'Pitching'],
            ['key' => 'closing', 'label' => 'Closing (Deal)'],
        ];
    }

    public function realisasiFor(int $employeeId, string $metricKey, string $date): int|float
    {
        return match ($metricKey) {
            'leads' => FoFunnel::where('employee_id', $employeeId)->whereDate('tgl_leads', $date)->count(),
            'ql' => FoFunnel::where('employee_id', $employeeId)->whereNotNull('tgl_qualified')->whereDate('tgl_qualified', $date)->count(),
            'pitching' => FoFunnelActivity::where('stage', 'pitching')
                ->whereDate('tanggal', $date)
                ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('pitching'))
                ->distinct('fo_funnel_id')
                ->count('fo_funnel_id'),
            'followup' => FoFunnelActivity::where('stage', 'followup')
                ->whereDate('tanggal', $date)
                ->whereHas('funnel', fn ($q) => $q->where('employee_id', $employeeId)->stageAtLeast('followup'))
                ->distinct('fo_funnel_id')
                ->count('fo_funnel_id'),
            'won' => FoFunnel::where('employee_id', $employeeId)->stageAtLeast('won')->whereDate('tgl_won', $date)->count(),
            'closing' => FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereDate('tgl_closing', $date)->count(),
            'nilai_closing' => (float) FoFunnel::where('employee_id', $employeeId)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereDate('tgl_closing', $date)->sum('nominal_investasi'),
            default => 0,
        };
    }

    public function yearlyTarget(int $employeeId, string $metricKey, int $year): float
    {
        return (float) (FoTarget::where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('metric_key', $metricKey)
            ->value('yearly_target') ?? 0);
    }

    public function dailyTarget(int $employeeId, string $metricKey, int $year): float
    {
        return $this->yearlyTarget($employeeId, $metricKey, $year) / 12 / 25;
    }

    /**
     * @return array{label: string, class: string}
     */
    public function statusFor(?float $percent): array
    {
        return match (true) {
            $percent === null => ['label' => 'RED', 'class' => 'badge-error'],
            $percent >= 100 => ['label' => 'GREEN', 'class' => 'badge-success'],
            $percent >= 50 => ['label' => 'YELLOW', 'class' => 'badge-warning'],
            default => ['label' => 'RED', 'class' => 'badge-error'],
        };
    }

    /**
     * Day-of-month => count for one metric, aggregated in a single DB query across the given
     * employees (instead of looping `realisasiFor()` per day, which would run 1 query per day).
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, int> day (1-31) => count
     */
    public function dailyCountsForMonth(array $employeeIds, string $metricKey, int $year, int $month): array
    {
        $driver = FoFunnel::query()->getConnection()->getDriverName();

        $dayExpr = fn (string $column) => $driver === 'sqlite'
            ? "CAST(strftime('%d', {$column}) AS INTEGER)"
            : "CAST(EXTRACT(DAY FROM {$column}) AS INTEGER)";

        $countsByDay = fn ($query, string $countExpr, string $dayColumn) => $query
            ->selectRaw($dayExpr($dayColumn).' as day, '.$countExpr.' as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $counts = match ($metricKey) {
            'leads' => $countsByDay(
                FoFunnel::whereIn('employee_id', $employeeIds)->whereYear('tgl_leads', $year)->whereMonth('tgl_leads', $month),
                'COUNT(*)',
                'tgl_leads'
            ),
            'ql' => $countsByDay(
                FoFunnel::whereIn('employee_id', $employeeIds)->whereNotNull('tgl_qualified')->whereYear('tgl_qualified', $year)->whereMonth('tgl_qualified', $month),
                'COUNT(*)',
                'tgl_qualified'
            ),
            'pitching' => $countsByDay(
                FoFunnelActivity::where('stage', 'pitching')
                    ->whereYear('tanggal', $year)->whereMonth('tanggal', $month)
                    ->whereHas('funnel', fn ($q) => $q->whereIn('employee_id', $employeeIds)->stageAtLeast('pitching')),
                'COUNT(DISTINCT fo_funnel_id)',
                'tanggal'
            ),
            'followup' => $countsByDay(
                FoFunnelActivity::where('stage', 'followup')
                    ->whereYear('tanggal', $year)->whereMonth('tanggal', $month)
                    ->whereHas('funnel', fn ($q) => $q->whereIn('employee_id', $employeeIds)->stageAtLeast('followup')),
                'COUNT(DISTINCT fo_funnel_id)',
                'tanggal'
            ),
            'won' => $countsByDay(
                FoFunnel::whereIn('employee_id', $employeeIds)->stageAtLeast('won')->whereYear('tgl_won', $year)->whereMonth('tgl_won', $month),
                'COUNT(*)',
                'tgl_won'
            ),
            'closing' => $countsByDay(
                FoFunnel::whereIn('employee_id', $employeeIds)->whereIn('stage', FoFunnel::CLOSED_STAGES)->whereYear('tgl_closing', $year)->whereMonth('tgl_closing', $month),
                'COUNT(*)',
                'tgl_closing'
            ),
            default => collect(),
        };

        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $result = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $result[$day] = (int) ($counts[$day] ?? 0);
        }

        return $result;
    }
}
