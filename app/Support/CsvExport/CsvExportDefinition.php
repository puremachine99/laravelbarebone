<?php

namespace App\Support\CsvExport;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CsvExportDefinition
{
    /**
     * @param  string  $routeName  Named route this export applies to (used to show the button on the matching page).
     * @param  string|null  $ability  Permission required to export, or null if the route's own middleware already gates access.
     * @param  array<int, string>  $headers  Column headers, in order.
     * @param  Closure(): Builder  $query  Builds the (already scoped/filtered) query to export. Kept free of custom
     *                                     orderBy — rows are streamed via chunkById, which needs to own the ordering
     *                                     to paginate safely through large tables.
     * @param  Closure(Model): array<int, string|int|null>  $mapRow  Maps one row to CSV column values, in $headers order.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $routeName,
        public readonly string $label,
        public readonly ?string $ability,
        public readonly array $headers,
        public readonly Closure $query,
        public readonly Closure $mapRow,
    ) {}

    public function filename(): string
    {
        return $this->slug.'-'.now()->format('Y-m-d').'.csv';
    }
}
