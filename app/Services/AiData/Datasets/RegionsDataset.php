<?php

namespace App\Services\AiData\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Regions with the provinces, regencies/cities and OPD mapped to them (OPD through their regency, Kementerian
 * directly).
 */
class RegionsDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('regions')
            ->leftJoin('regencies', 'regencies.region_id', '=', 'regions.id')
            ->leftJoin('pmo_opds', fn ($join) => $join
                ->on('pmo_opds.regency_id', '=', 'regencies.id')
                ->orOn('pmo_opds.region_id', '=', 'regions.id'));
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
