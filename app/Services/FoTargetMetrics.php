<?php

namespace App\Services;

use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use App\Models\FoTarget;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

    /**
     * The same numbers as `realisasiFor()` for every metric of one day, for many employees at once: one grouped query
     * per metric instead of one query per employee per metric.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<string, array<int, int|float>> metric key => employee id => realisasi (0 when missing)
     */
    public function realisasiByEmployeeForDate(array $employeeIds, string $date): array
    {
        $from = Carbon::parse($date)->toDateString();
        $to = Carbon::parse($date)->addDay()->toDateString();
        $onDay = fn ($query, string $column) => $query->where($column, '>=', $from)->where($column, '<', $to);

        $perEmployee = fn ($query, string $aggregate) => $query
            ->whereIn('employee_id', $employeeIds)
            ->selectRaw("employee_id, {$aggregate} as total")
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $distinctFunnelsPerEmployee = function (string $stage) use ($employeeIds, $onDay) {
            $funnelOwners = FoFunnel::whereIn('employee_id', $employeeIds)->stageAtLeast($stage)->pluck('employee_id', 'id');

            if ($funnelOwners->isEmpty()) {
                return collect();
            }

            return $onDay(FoFunnelActivity::where('stage', $stage)->whereIn('fo_funnel_id', $funnelOwners->keys()), 'tanggal')
                ->distinct()
                ->pluck('fo_funnel_id')
                ->countBy(fn ($funnelId) => $funnelOwners[$funnelId]);
        };

        $closedRows = $onDay(FoFunnel::whereIn('stage', FoFunnel::CLOSED_STAGES), 'tgl_closing')
            ->whereIn('employee_id', $employeeIds)
            ->selectRaw('employee_id, COUNT(*) as total, COALESCE(SUM(nominal_investasi), 0) as nilai')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $totals = [
            'leads' => $perEmployee($onDay(FoFunnel::query(), 'tgl_leads'), 'COUNT(*)'),
            'ql' => $perEmployee($onDay(FoFunnel::whereNotNull('tgl_qualified'), 'tgl_qualified'), 'COUNT(*)'),
            'pitching' => $distinctFunnelsPerEmployee('pitching'),
            'followup' => $distinctFunnelsPerEmployee('followup'),
            'won' => $perEmployee($onDay(FoFunnel::stageAtLeast('won'), 'tgl_won'), 'COUNT(*)'),
            'closing' => $closedRows->map(fn ($row) => (int) $row->total),
            'nilai_closing' => $closedRows->map(fn ($row) => (float) $row->nilai),
        ];

        return collect($totals)->map(fn ($byEmployee, $metricKey) => collect($employeeIds)
            ->mapWithKeys(fn ($id) => [$id => $metricKey === 'nilai_closing' ? (float) ($byEmployee[$id] ?? 0) : (int) ($byEmployee[$id] ?? 0)])
            ->all()
        )->all();
    }

    /**
     * Every yearly target of the given employees for one year, in a single query.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, float>> employee id => metric key => yearly target
     */
    public function yearlyTargetsByEmployee(array $employeeIds, int $year): array
    {
        return FoTarget::whereIn('employee_id', $employeeIds)
            ->where('year', $year)
            ->get(['employee_id', 'metric_key', 'yearly_target'])
            ->groupBy('employee_id')
            ->map(fn ($targets) => $targets->mapWithKeys(fn ($target) => [$target->metric_key => (float) $target->yearly_target])->all())
            ->all();
    }

    /**
     * Leads, won, closing and deal value per employee for one month: four numbers per FO in two grouped queries.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, array{lead: int, won: int, closing: int, nilai_closing: float}> employee id => totals
     */
    public function monthlyTotalsByEmployee(array $employeeIds, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->toDateString();
        $to = Carbon::create($year, $month, 1)->addMonth()->toDateString();
        $inMonth = fn ($query, string $column) => $query->whereIn('employee_id', $employeeIds)->where($column, '>=', $from)->where($column, '<', $to);

        $countPerEmployee = fn ($query) => $query->selectRaw('employee_id, COUNT(*) as total')->groupBy('employee_id')->pluck('total', 'employee_id');

        $leads = $countPerEmployee($inMonth(FoFunnel::query(), 'tgl_leads'));
        $won = $countPerEmployee($inMonth(FoFunnel::stageAtLeast('won'), 'tgl_won'));
        $closed = $inMonth(FoFunnel::whereIn('stage', FoFunnel::CLOSED_STAGES), 'tgl_closing')
            ->selectRaw('employee_id, COUNT(*) as total, COALESCE(SUM(nominal_investasi), 0) as nilai')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        return collect($employeeIds)->mapWithKeys(fn ($id) => [$id => [
            'lead' => (int) ($leads[$id] ?? 0),
            'won' => (int) ($won[$id] ?? 0),
            'closing' => (int) ($closed[$id]->total ?? 0),
            'nilai_closing' => (float) ($closed[$id]->nilai ?? 0),
        ]])->all();
    }

    /**
     * Caches month-level dashboard data (heatmap, leaderboard) per month and FO scope. The key carries a version that
     * FoFunnel / FoFunnelActivity bump on every change, so a Pipeline edit shows up on the next render.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function rememberForMonth(string $name, array $employeeIds, int $year, int $month, Closure $callback): mixed
    {
        $version = (int) Cache::get('fo-metrics:version', 0);
        $scope = md5(implode(',', collect($employeeIds)->sort()->all()));

        return Cache::remember("fo-metrics:{$name}:v{$version}:{$year}-{$month}:{$scope}", 600, $callback);
    }

    /**
     * Called by FoFunnel / FoFunnelActivity whenever they change: invalidates everything cached by `rememberForMonth()`.
     */
    public static function flushMonthlyCache(): void
    {
        Cache::forever('fo-metrics:version', (int) Cache::get('fo-metrics:version', 0) + 1);
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
