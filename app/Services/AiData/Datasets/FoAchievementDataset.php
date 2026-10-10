<?php

namespace App\Services\AiData\Datasets;

use App\Models\FoFunnel;
use App\Services\AiData\Dataset;
use App\Services\AiData\SqlMonth;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Funding Officer funnel results and closing-value achievement against the saved yearly target, using the same
 * realisasi rules as the FO Management page. Only saved targets count: a year without a saved `nilai_closing`
 * target contributes no target (the page's "suggested target" is a guess and is not used here).
 *
 * Per person by design: only the employee's name and department are read, never NIK, NPWP or any other
 * employee field. Offboarded employees are kept so all-time rankings stay complete.
 */
class FoAchievementDataset implements Dataset
{
    private const COUNT_METRICS = ['leads', 'ql', 'pitching', 'won', 'closing'];

    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $groupBy, ?array $departmentIds, array $filters = []): Collection
    {
        $byMonth = in_array('month', $groupBy, true);
        $period = [$from->toDateString(), $to->toDateString()];
        $base = [];

        $add = function (Collection $results, string $metric) use (&$base): void {
            foreach ($results as $result) {
                $key = $result->employee_id.'|'.($result->month ?? '');
                $base[$key] ??= ['employee_id' => (int) $result->employee_id, 'month' => $result->month ?? null];
                $base[$key][$metric] = ($base[$key][$metric] ?? 0) + (float) $result->value;
            }
        };

        $add($this->aggregate($this->funnels($departmentIds), 'fo_funnels.tgl_leads', 'COUNT(*)', $period, $byMonth), 'leads');
        $add($this->aggregate($this->funnels($departmentIds)->whereNotNull('fo_funnels.tgl_qualified'), 'fo_funnels.tgl_qualified', 'COUNT(*)', $period, $byMonth), 'ql');
        $add($this->aggregate($this->funnels($departmentIds)->whereIn('fo_funnels.stage', $this->stagesFrom('won')), 'fo_funnels.tgl_won', 'COUNT(*)', $period, $byMonth), 'won');
        $add($this->aggregate($this->funnels($departmentIds)->whereIn('fo_funnels.stage', FoFunnel::CLOSED_STAGES), 'fo_funnels.tgl_closing', 'COUNT(*)', $period, $byMonth), 'closing');
        $add($this->aggregate($this->funnels($departmentIds)->whereIn('fo_funnels.stage', FoFunnel::CLOSED_STAGES), 'fo_funnels.tgl_closing', 'COALESCE(SUM(fo_funnels.nominal_investasi), 0)', $period, $byMonth), 'nilai_closing');

        $pitching = $this->funnels($departmentIds)
            ->join('fo_funnel_activities', 'fo_funnel_activities.fo_funnel_id', '=', 'fo_funnels.id')
            ->whereNull('fo_funnel_activities.deleted_at')
            ->where('fo_funnel_activities.stage', 'pitching')
            ->whereIn('fo_funnels.stage', $this->stagesFrom('pitching'));
        $add($this->aggregate($pitching, 'fo_funnel_activities.tanggal', 'COUNT(DISTINCT fo_funnel_activities.fo_funnel_id)', $period, $byMonth), 'pitching');

        $this->addTargets($base, $from, $to, $byMonth, $departmentIds);

        return $this->rollUp($base, $groupBy);
    }

    public function availableFrom(): ?CarbonImmutable
    {
        $first = DB::table('fo_funnels')->whereNull('deleted_at')->min('tgl_leads');

        return $first ? CarbonImmutable::parse($first)->startOfMonth() : null;
    }

    /**
     * @param  array<int, int>|null  $departmentIds
     */
    private function funnels(?array $departmentIds): Builder
    {
        return DB::table('fo_funnels')
            ->join('employees', 'employees.id', '=', 'fo_funnels.employee_id')
            ->whereNull('fo_funnels.deleted_at')
            ->when($departmentIds !== null, fn (Builder $query) => $query->whereIn('employees.department_id', $departmentIds));
    }

    /**
     * @param  array{0: string, 1: string}  $period
     * @return Collection<int, object>
     */
    private function aggregate(Builder $query, string $dateColumn, string $aggregate, array $period, bool $byMonth): Collection
    {
        $query->whereBetween($dateColumn, $period)
            ->selectRaw("fo_funnels.employee_id AS employee_id, {$aggregate} AS value")
            ->groupBy('fo_funnels.employee_id');

        if ($byMonth) {
            $month = SqlMonth::of($dateColumn);
            $query->selectRaw("{$month} AS month")->groupByRaw($month);
        }

        return $query->get();
    }

    /**
     * Spreads each saved yearly `nilai_closing` target evenly over its 12 months, and a month's share over its days,
     * so any period (whole months or a date range such as the last 30 days) gets its share.
     *
     * @param  array<string, array<string, mixed>>  $base
     * @param  array<int, int>|null  $departmentIds
     */
    private function addTargets(array &$base, CarbonImmutable $from, CarbonImmutable $to, bool $byMonth, ?array $departmentIds): void
    {
        $yearly = DB::table('fo_targets')
            ->join('employees', 'employees.id', '=', 'fo_targets.employee_id')
            ->where('fo_targets.metric_key', 'nilai_closing')
            ->whereBetween('fo_targets.year', [$from->year, $to->year])
            ->when($departmentIds !== null, fn (Builder $query) => $query->whereIn('employees.department_id', $departmentIds))
            ->get(['fo_targets.employee_id', 'fo_targets.year', 'fo_targets.yearly_target'])
            ->groupBy('employee_id');

        foreach (CarbonPeriod::create($from->startOfMonth(), '1 month', $to->startOfMonth()) as $month) {
            $month = CarbonImmutable::instance($month);
            $coveredDays = $month->max($from)->startOfDay()->diffInDays($month->endOfMonth()->min($to)->startOfDay()) + 1;
            $shareOfMonth = $coveredDays / $month->daysInMonth;

            foreach ($yearly as $employeeId => $targets) {
                $target = $targets->firstWhere('year', $month->year);

                if (! $target) {
                    continue;
                }

                $key = $employeeId.'|'.($byMonth ? $month->format('Y-m') : '');
                $base[$key] ??= ['employee_id' => (int) $employeeId, 'month' => $byMonth ? $month->format('Y-m') : null];
                $base[$key]['target_nilai_closing'] = ($base[$key]['target_nilai_closing'] ?? 0) + (float) $target->yearly_target / 12 * $shareOfMonth;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $base
     * @param  array<int, string>  $groupBy
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private function rollUp(array $base, array $groupBy): Collection
    {
        $people = DB::table('employees')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->whereIn('employees.id', array_unique(array_column($base, 'employee_id')))
            ->get(['employees.id', 'users.name AS name', 'departments.name AS department'])
            ->keyBy('id');

        $dimension = fn (array $row, string $key): string => match ($key) {
            'employee' => $people->get($row['employee_id'])?->name ?? 'Tanpa nama',
            'department' => $people->get($row['employee_id'])?->department ?? 'Tanpa departemen',
            'month' => (string) $row['month'],
        };

        return collect($base)
            ->groupBy(fn (array $row) => implode('|', array_map(fn (string $key) => $key === 'employee' ? $row['employee_id'] : $dimension($row, $key), $groupBy)))
            ->map(function (Collection $rows) use ($groupBy, $dimension) {
                $row = collect($groupBy)->mapWithKeys(fn (string $key) => [$key => $dimension($rows->first(), $key)])->all();

                foreach (self::COUNT_METRICS as $metric) {
                    $row[$metric] = (int) $rows->sum($metric);
                }

                $row['nilai_closing'] = round((float) $rows->sum('nilai_closing'), 2);
                $row['target_nilai_closing'] = round((float) $rows->sum('target_nilai_closing'), 2);
                $row['achievement_pct'] = $row['target_nilai_closing'] > 0
                    ? round($row['nilai_closing'] / $row['target_nilai_closing'] * 100, 1)
                    : null;
                $row['group_size'] = $rows->pluck('employee_id')->unique()->count();

                return $row;
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function stagesFrom(string $stage): array
    {
        $minimum = FoFunnel::STAGE_ORDER[$stage];

        return array_keys(array_filter(FoFunnel::STAGE_ORDER, fn (int $rank) => $rank >= $minimum));
    }
}
