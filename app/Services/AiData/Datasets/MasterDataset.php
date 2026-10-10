<?php

namespace App\Services\AiData\Datasets;

use App\Services\AiData\Dataset;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Base for read-only master data (employees, departments, roles, regions, OPD, urusan, products). Not time based:
 * the period is ignored. A subclass only declares its query and the SQL behind each dimension, metric and filter;
 * nothing outside those declarations is ever selected.
 */
abstract class MasterDataset implements Dataset
{
    /**
     * Base query with every join the dimensions, metrics and filters need.
     */
    abstract protected function query(): Builder;

    /**
     * @return array<string, string> dimension => SQL expression (never NULL; use COALESCE)
     */
    abstract protected function dimensions(): array;

    /**
     * @return array<string, string> metric => SQL aggregate
     */
    abstract protected function metrics(): array;

    /**
     * The distinct thing a row is built from, for the minimum group size rule.
     */
    abstract protected function groupSizeExpression(): string;

    /**
     * @return array<string, string|array<int, string>> filter => SQL expression(s), matched case-insensitively and
     *                                                  partially; several expressions match if any of them does
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Column limited to the user's departments for department-scoped datasets.
     */
    protected function departmentColumn(): ?string
    {
        return null;
    }

    public function rows(CarbonImmutable $from, CarbonImmutable $to, array $groupBy, ?array $departmentIds, array $filters = []): Collection
    {
        $query = $this->query();
        $dimensions = $this->dimensions();

        if ($departmentIds !== null && $this->departmentColumn() !== null) {
            $query->whereIn($this->departmentColumn(), $departmentIds);
        }

        foreach ($filters as $key => $value) {
            $needle = '%'.mb_strtolower(trim($value)).'%';

            $query->where(function (Builder $query) use ($key, $needle) {
                foreach ((array) $this->filters()[$key] as $expression) {
                    $query->orWhereRaw("LOWER({$expression}) LIKE ?", [$needle]);
                }
            });
        }

        $query->selectRaw(collect($this->metrics())
            ->map(fn (string $aggregate, string $metric) => "{$aggregate} AS {$metric}")
            ->push("COUNT(DISTINCT {$this->groupSizeExpression()}) AS group_size")
            ->implode(', '));

        foreach ($groupBy as $dimension) {
            $query->selectRaw("{$dimensions[$dimension]} AS {$dimension}")->groupByRaw($dimensions[$dimension]);
        }

        return $query->get()->map(fn (object $row): array => [
            ...collect($groupBy)->mapWithKeys(fn (string $dimension) => [$dimension => (string) $row->{$dimension}])->all(),
            ...collect($this->metrics())->mapWithKeys(fn (string $aggregate, string $metric) => [$metric => (int) $row->{$metric}])->all(),
            'group_size' => (int) $row->group_size,
        ]);
    }

    public function availableFrom(): ?CarbonImmutable
    {
        return null;
    }
}
