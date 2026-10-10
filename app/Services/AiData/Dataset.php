<?php

namespace App\Services\AiData;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One entry of config/ai_datasets.php. Implementations return aggregates only and never select personal columns.
 */
interface Dataset
{
    /**
     * Aggregated rows for the period. Each row holds the requested dimension keys, every metric of the dataset, and
     * `group_size`: the number of distinct people behind the row, used for the minimum group size rule.
     *
     * Datasets marked `time_based => false` in the catalog (master data) ignore $from and $to.
     *
     * @param  array<int, string>  $groupBy  dimension keys, already validated against the catalog
     * @param  array<int, int>|null  $departmentIds  null = every department; [] = nothing
     * @param  array<string, string>  $filters  the dataset's own filters (keys validated against the catalog); for
     *                                          department-scoped datasets the department filter is already in $departmentIds
     * @return Collection<int, array<string, int|float|string|null>>
     */
    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $groupBy, ?array $departmentIds, array $filters = []): Collection;

    /**
     * First month that has data, used as the start of an `all_time` request. null = no data yet.
     */
    public function availableFrom(): ?CarbonImmutable;
}
