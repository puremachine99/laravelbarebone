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
     * @param  array<int, string>  $groupBy  dimension keys, already validated against the catalog
     * @param  array<int, int>|null  $departmentIds  null = every department; [] = nothing
     * @return Collection<int, array<string, int|string>>
     */
    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $groupBy, ?array $departmentIds): Collection;

    /**
     * First month that has data, used as the start of an `all_time` request. null = no data yet.
     */
    public function availableFrom(): ?CarbonImmutable;
}
