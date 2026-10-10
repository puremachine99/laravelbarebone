<?php

namespace App\Services\AiData;

use App\Models\AiDataQueryLog;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Runs one structured data request on behalf of a user. The AI only sends parameters; this class decides what may
 * be read: the dataset must be in the catalog and allowed for the user, rows are limited to the user's department
 * unless they may see all, small groups are hidden, and every request (allowed or denied) is written to the audit log.
 *
 * Request shape: {dataset, group_by?: string[], metrics?: string[], filters?: {department?: string},
 * from?: "YYYY-MM", to?: "YYYY-MM", from_date?: "YYYY-MM-DD", to_date?: "YYYY-MM-DD", all_time?: bool,
 * compare_previous?: bool, sort?: string,
 * direction?: "asc"|"desc", limit?: int}
 */
class DataQueryService
{
    private const ALLOWED_KEYS = ['dataset', 'group_by', 'metrics', 'filters', 'from', 'to', 'from_date', 'to_date', 'all_time', 'compare_previous', 'sort', 'direction', 'limit'];

    private const COMPARISON_SUFFIXES = ['_previous', '_delta', '_delta_pct'];

    public function __construct(private DatasetCatalog $catalog) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{dataset: string, label: string, period: array{from: string, to: string}, compared_to: array{from: string, to: string}|null, scope: string, group_by: array<int, string>, metrics: array<int, string>, rows: array<int, array<string, int|float|string|null>>, suppressed_rows: int, truncated: bool, notes: array<int, string>}
     *
     * @throws AuthorizationException when the user may not read this dataset
     * @throws ValidationException when the request is not allowed by the catalog
     */
    public function run(User $user, array $params): array
    {
        $key = is_string($params['dataset'] ?? null) ? $params['dataset'] : '';
        $dataset = $this->catalog->find($key);

        if ($dataset === null) {
            throw ValidationException::withMessages(['dataset' => 'Dataset tidak dikenal.']);
        }

        if (! $this->catalog->canUse($user, $key)) {
            $this->log($user, $key, 'denied', $params);

            throw new AuthorizationException('Anda tidak punya akses ke data ini.');
        }

        $validated = $this->validate($params, $dataset);

        $groupBy = array_values(array_unique($validated['group_by'] ?? []));
        $metrics = array_values(array_unique($validated['metrics'] ?? array_keys($dataset['metrics'])));
        $comparePrevious = (bool) ($validated['compare_previous'] ?? false);
        $limit = (int) ($validated['limit'] ?? config('ai_datasets.max_rows'));
        $isTimeBased = $dataset['time_based'] ?? true;
        $isDepartmentScoped = $dataset['department_scoped'] ?? true;
        $seesAllDepartments = ! $isDepartmentScoped || $this->catalog->canSeeAllDepartments($user);
        $filters = array_filter($validated['filters'] ?? [], fn ($value) => $value !== null && $value !== '');

        // For department-scoped datasets the department filter can only narrow the user's scope; every other filter
        // is applied by the dataset itself.
        $departmentIds = $isDepartmentScoped ? $this->departmentScope($user, $seesAllDepartments, $filters['department'] ?? null) : null;
        $datasetFilters = $isDepartmentScoped ? array_diff_key($filters, ['department' => true]) : $filters;

        /** @var Dataset $source */
        $source = app($dataset['source']);

        [$from, $to, $byDate] = $this->period($validated, $source);
        $format = $byDate ? 'Y-m-d' : 'Y-m';

        $rows = $source->rows($from, $to, $groupBy, $departmentIds, $datasetFilters);

        $previousPeriod = null;

        if ($comparePrevious) {
            $previousPeriod = $this->previousPeriod($from, $to, $byDate);
            $rows = $this->withComparison($rows, $source->rows($previousPeriod[0], $previousPeriod[1], $groupBy, $departmentIds, $datasetFilters), $groupBy, array_keys($dataset['metrics']));
        }

        $minGroupSize = $this->catalog->minGroupSize($key);
        $visible = $rows->filter(fn (array $row) => $this->isLargeEnough($row, $minGroupSize, $comparePrevious));
        $suppressed = $rows->count() - $visible->count();

        $output = $this->sorted($visible, $groupBy, $validated['sort'] ?? null, $validated['direction'] ?? 'desc')
            ->map(fn (array $row) => $this->onlyRequested($row, $groupBy, $metrics, $comparePrevious));

        $this->log($user, $key, 'ok', $validated, min($output->count(), $limit), $suppressed);

        return [
            'dataset' => $key,
            'label' => $dataset['label'],
            'period' => $isTimeBased ? ['from' => $from->format($format), 'to' => $to->format($format)] : null,
            'compared_to' => $previousPeriod ? ['from' => $previousPeriod[0]->format($format), 'to' => $previousPeriod[1]->format($format)] : null,
            'scope' => match (true) {
                ! $isDepartmentScoped => 'global',
                $seesAllDepartments => 'all_departments',
                default => 'own_department',
            },
            'group_by' => $groupBy,
            'metrics' => $metrics,
            'rows' => $output->take($limit)->all(),
            'suppressed_rows' => $suppressed,
            'truncated' => $output->count() > $limit,
            'notes' => array_values(array_filter([
                $suppressed > 0 ? "{$suppressed} baris disembunyikan karena berisi kurang dari {$minGroupSize} orang (menjaga privasi)." : null,
                $seesAllDepartments ? null : 'Data dibatasi ke departemen pengguna.',
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array{dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>}  $dataset
     * @return array<string, mixed>
     */
    private function validate(array $params, array $dataset): array
    {
        $validator = Validator::make($params, [
            'dataset' => ['required', 'string'],
            'group_by' => ['sometimes', 'array'],
            'group_by.*' => ['string', Rule::in(array_keys($dataset['dimensions']))],
            'metrics' => ['sometimes', 'array', 'min:1'],
            'metrics.*' => ['string', Rule::in(array_keys($dataset['metrics']))],
            'filters' => ['sometimes', 'array'],
            'filters.*' => ['nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'all_time' => ['sometimes', 'boolean'],
            'compare_previous' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.config('ai_datasets.max_rows')],
        ]);

        $validator->after(function ($validator) use ($params, $dataset) {
            $unknown = array_diff(array_keys($params), self::ALLOWED_KEYS);

            if ($unknown !== []) {
                $validator->errors()->add('params', 'Parameter tidak dikenal: '.implode(', ', $unknown).'.');
            }

            if (is_array($params['filters'] ?? null)) {
                $unknownFilters = array_diff(array_keys($params['filters']), $dataset['filters']);

                if ($unknownFilters !== []) {
                    $validator->errors()->add('filters', 'Filter tidak dikenal: '.implode(', ', $unknownFilters).'.');
                }
            }

            if (($params['compare_previous'] ?? false) && in_array('month', (array) ($params['group_by'] ?? []), true)) {
                $validator->errors()->add('compare_previous', 'Perbandingan periode tidak bisa digabung dengan pengelompokan per bulan.');
            }

            $usesMonths = isset($params['from']) || isset($params['to']);
            $usesDates = isset($params['from_date']) || isset($params['to_date']);

            if (! ($dataset['time_based'] ?? true) && ($usesMonths || $usesDates || ($params['all_time'] ?? false) || ($params['compare_previous'] ?? false))) {
                $validator->errors()->add('params', 'Dataset ini tidak berbasis waktu: jangan kirim periode atau compare_previous.');
            }

            if ($usesMonths && $usesDates) {
                $validator->errors()->add('from_date', 'Pakai from/to (bulan) atau from_date/to_date (tanggal), jangan keduanya.');
            }

            if (isset($params['to_date']) && ! isset($params['from_date'])) {
                $validator->errors()->add('from_date', 'from_date wajib diisi bila to_date diisi.');
            }

            if ($params['all_time'] ?? false) {
                if ($usesMonths || $usesDates) {
                    $validator->errors()->add('all_time', 'all_time tidak bisa digabung dengan from/to atau from_date/to_date.');
                }

                if ($params['compare_previous'] ?? false) {
                    $validator->errors()->add('all_time', 'all_time tidak bisa digabung dengan compare_previous.');
                }
            }

            if (isset($params['sort']) && is_string($params['sort'])) {
                $sortable = array_keys($dataset['metrics']);

                if ($params['compare_previous'] ?? false) {
                    foreach (array_keys($dataset['metrics']) as $metric) {
                        foreach (self::COMPARISON_SUFFIXES as $suffix) {
                            $sortable[] = $metric.$suffix;
                        }
                    }
                }

                if (! in_array($params['sort'], $sortable, true)) {
                    $validator->errors()->add('sort', 'Kolom urutan tidak dikenal.');
                }
            }

            $from = $params['from'] ?? null;
            $to = $params['to'] ?? null;

            if (is_string($from) && is_string($to) && preg_match('/^\d{4}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}$/', $to)) {
                $start = CarbonImmutable::createFromFormat('Y-m-d', "{$from}-01");
                $end = CarbonImmutable::createFromFormat('Y-m-d', "{$to}-01");

                if ($end->lessThan($start)) {
                    $validator->errors()->add('to', 'Bulan akhir tidak boleh sebelum bulan awal.');
                } elseif ((int) round($start->diffInMonths($end)) + 1 > config('ai_datasets.max_period_months')) {
                    $validator->errors()->add('to', 'Periode maksimal '.config('ai_datasets.max_period_months').' bulan.');
                }
            }

            $fromDate = $params['from_date'] ?? null;
            $toDate = $params['to_date'] ?? null;

            if (is_string($fromDate) && is_string($toDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                $start = CarbonImmutable::parse($fromDate);
                $end = CarbonImmutable::parse($toDate);

                if ($end->lessThan($start)) {
                    $validator->errors()->add('to_date', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
                } elseif ($start->addMonths(config('ai_datasets.max_period_months'))->lessThanOrEqualTo($end)) {
                    $validator->errors()->add('to_date', 'Periode maksimal '.config('ai_datasets.max_period_months').' bulan.');
                }
            }
        });

        return $validator->validate();
    }

    /**
     * Start, end, and whether the request was made in days (from_date/to_date) rather than whole months.
     * Without any period the current month is used; `all_time` runs from the dataset's first data to this month.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    private function period(array $validated, Dataset $source): array
    {
        if ($validated['all_time'] ?? false) {
            return [$source->availableFrom() ?? CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth(), false];
        }

        if (isset($validated['from_date'])) {
            $from = CarbonImmutable::parse($validated['from_date'])->startOfDay();
            $to = isset($validated['to_date']) ? CarbonImmutable::parse($validated['to_date'])->endOfDay() : CarbonImmutable::now()->endOfDay();

            return [$from, $to, true];
        }

        $from = CarbonImmutable::createFromFormat('Y-m-d', ($validated['from'] ?? now()->format('Y-m')).'-01')->startOfMonth();
        $to = CarbonImmutable::createFromFormat('Y-m-d', ($validated['to'] ?? $from->format('Y-m')).'-01')->endOfMonth();

        return [$from, $to, false];
    }

    /**
     * The period of the same length right before: the same number of months, or of days for a date range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousPeriod(CarbonImmutable $from, CarbonImmutable $to, bool $byDate): array
    {
        if ($byDate) {
            $days = (int) round($from->diffInDays($to->startOfDay())) + 1;

            return [$from->subDays($days), $from->subDay()->endOfDay()];
        }

        $months = (int) round($from->diffInMonths($to->startOfMonth())) + 1;

        return [$from->subMonths($months), $from->subMonth()->endOfMonth()];
    }

    /**
     * null = every department. A user without the all-departments permission is limited to their own department
     * (none at all if they have no department). The department filter can only narrow that scope, never widen it.
     * The employee record is the source of truth for the department; users.department_id is only a synced copy.
     *
     * @return array<int, int>|null
     */
    private function departmentScope(User $user, bool $seesAllDepartments, ?string $departmentName): ?array
    {
        $scope = $seesAllDepartments ? null : array_values(array_filter([$user->employee?->department_id ?? $user->department_id]));

        if ($departmentName === null || $departmentName === '') {
            return $scope;
        }

        $matching = Department::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($departmentName)).'%'])
            ->pluck('id')
            ->all();

        return $scope === null ? $matching : array_values(array_intersect($scope, $matching));
    }

    /**
     * Joins the previous period onto the current rows and computes the change server side, so the AI never has to
     * do the arithmetic.
     *
     * @param  Collection<int, array<string, int|string>>  $current
     * @param  Collection<int, array<string, int|string>>  $previous
     * @param  array<int, string>  $groupBy
     * @param  array<int, string>  $metrics
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private function withComparison(Collection $current, Collection $previous, array $groupBy, array $metrics): Collection
    {
        $keyOf = fn (array $row) => implode('|', array_map(fn (string $dimension) => $row[$dimension], $groupBy));
        $currentByKey = $current->keyBy($keyOf);
        $previousByKey = $previous->keyBy($keyOf);

        return $currentByKey->keys()->merge($previousByKey->keys())->unique()->map(function (int|string $key) use ($currentByKey, $previousByKey, $groupBy, $metrics) {
            $now = $currentByKey->get($key);
            $before = $previousByKey->get($key);
            $row = collect($groupBy)->mapWithKeys(fn (string $dimension) => [$dimension => ($now ?? $before)[$dimension]])->all();

            foreach ($metrics as $metric) {
                $isRatio = str_ends_with($metric, '_pct');
                $currentValue = $now[$metric] ?? ($isRatio ? null : 0);
                $previousValue = $before[$metric] ?? ($isRatio ? null : 0);
                $delta = match (true) {
                    $currentValue === null || $previousValue === null => null,
                    is_int($currentValue) && is_int($previousValue) => $currentValue - $previousValue,
                    default => round($currentValue - $previousValue, 2),
                };

                $row[$metric] = $currentValue;
                $row["{$metric}_previous"] = $previousValue;
                $row["{$metric}_delta"] = $delta;
                $row["{$metric}_delta_pct"] = ! $isRatio && $previousValue > 0 ? round(($currentValue - $previousValue) / $previousValue * 100, 1) : null;
            }

            $row['group_size'] = (int) ($now['group_size'] ?? 0);
            $row['group_size_previous'] = (int) ($before['group_size'] ?? 0);

            return $row;
        })->values();
    }

    /**
     * Sorts on a metric (ranking) before the row limit is applied, so "top 5" really is the top 5. Rows without a
     * value for that metric go last. Without a sort, rows are ordered by their dimensions.
     *
     * @param  Collection<int, array<string, int|float|string|null>>  $rows
     * @param  array<int, string>  $groupBy
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private function sorted(Collection $rows, array $groupBy, ?string $sort, string $direction): Collection
    {
        if ($sort === null) {
            return $rows->sortBy(fn (array $row) => implode('|', array_map(fn (string $dimension) => $row[$dimension], $groupBy)))->values();
        }

        [$withValue, $withoutValue] = $rows->partition(fn (array $row) => ($row[$sort] ?? null) !== null);

        return $withValue->sortBy(fn (array $row) => $row[$sort], SORT_REGULAR, $direction === 'desc')
            ->concat($withoutValue)
            ->values();
    }

    /**
     * A period with no activity reveals nothing; a period with activity from fewer than the minimum number of people
     * would reveal individuals, so the whole row is hidden.
     *
     * @param  array<string, int|float|string|null>  $row
     */
    private function isLargeEnough(array $row, int $minGroupSize, bool $comparePrevious): bool
    {
        $sizes = $comparePrevious ? [$row['group_size'], $row['group_size_previous']] : [$row['group_size']];

        return collect($sizes)->every(fn (int $size) => $size === 0 || $size >= $minGroupSize);
    }

    /**
     * @param  array<string, int|float|string|null>  $row
     * @param  array<int, string>  $groupBy
     * @param  array<int, string>  $metrics
     * @return array<string, int|float|string|null>
     */
    private function onlyRequested(array $row, array $groupBy, array $metrics, bool $comparePrevious): array
    {
        $keys = $groupBy;

        foreach ($metrics as $metric) {
            $keys[] = $metric;

            if ($comparePrevious) {
                array_push($keys, "{$metric}_previous", "{$metric}_delta", "{$metric}_delta_pct");
            }
        }

        return array_intersect_key($row, array_flip($keys));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function log(User $user, string $dataset, string $outcome, array $params, int $rowCount = 0, int $suppressedRows = 0): void
    {
        AiDataQueryLog::create([
            'user_id' => $user->id,
            'dataset' => $dataset,
            'outcome' => $outcome,
            'parameters' => array_intersect_key($params, array_flip(self::ALLOWED_KEYS)),
            'row_count' => $rowCount,
            'suppressed_rows' => $suppressedRows,
        ]);
    }
}
