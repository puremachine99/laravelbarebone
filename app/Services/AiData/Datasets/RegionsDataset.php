<?php

namespace App\Services\AiData\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Regions with the provinces, regencies/cities and OPD mapped to them.
 */
class RegionsDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('regions')
            ->leftJoin('regencies', 'regencies.region_id', '=', 'regions.id')
            ->leftJoin('pmo_opds', 'pmo_opds.regency_id', '=', 'regencies.id');
    }

    protected function dimensions(): array
    {
        return [
            'region' => 'regions.name',
            'code' => "COALESCE(regions.code, '-')",
        ];
    }

    protected function metrics(): array
    {
        return [
            'provinces' => 'COUNT(DISTINCT regencies.province_id)',
            'regencies' => 'COUNT(DISTINCT regencies.id)',
            'opds' => 'COUNT(DISTINCT pmo_opds.id)',
        ];
    }

    protected function filters(): array
    {
        return ['name' => ['regions.name', "COALESCE(regions.code, '')"]];
    }

    protected function groupSizeExpression(): string
    {
        return 'regions.id';
    }
}
