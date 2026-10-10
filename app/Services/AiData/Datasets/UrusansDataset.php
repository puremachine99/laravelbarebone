<?php

namespace App\Services\AiData\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Bidang urusan with how many OPD carry each one.
 */
class UrusansDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('pmo_urusans')
            ->leftJoin('pmo_opd_urusan', 'pmo_opd_urusan.urusan_id', '=', 'pmo_urusans.id')
            ->leftJoin('pmo_opds', 'pmo_opds.id', '=', 'pmo_opd_urusan.opd_id');
    }

    protected function dimensions(): array
    {
        return [
            'urusan' => 'pmo_urusans.urusan',
            'code' => 'pmo_urusans.code',
        ];
    }

    protected function metrics(): array
    {
        return ['opds' => 'COUNT(DISTINCT pmo_opds.id)'];
    }

    protected function filters(): array
    {
        return ['name' => ['pmo_urusans.urusan', 'pmo_urusans.code']];
    }

    protected function groupSizeExpression(): string
    {
        return 'pmo_urusans.id';
    }
}
