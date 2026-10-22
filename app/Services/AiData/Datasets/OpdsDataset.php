<?php

namespace App\Services\AiData\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * OPD and other government institutions, with their regency/city, province, region and bidang urusan.
 * Kementerian have no regency/province; their region is their own pmo_opds.region_id.
 */
class OpdsDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('pmo_opds')
            ->leftJoin('regencies', 'regencies.id', '=', 'pmo_opds.regency_id')
            ->leftJoin('provinces', 'provinces.id', '=', 'regencies.province_id')
            ->leftJoin('regions', 'regions.id', '=', DB::raw('COALESCE(pmo_opds.region_id, regencies.region_id)'))
            ->leftJoin('pmo_opd_urusan', 'pmo_opd_urusan.opd_id', '=', 'pmo_opds.id')
            ->leftJoin('pmo_urusans', 'pmo_urusans.id', '=', 'pmo_opd_urusan.urusan_id');
    }

    protected function dimensions(): array
    {
        return [
            'opd' => 'pmo_opds.name',
            'jenis' => "COALESCE(pmo_opds.jenis, '-')",
            'regency' => "COALESCE(regencies.name, '-')",
            'province' => "COALESCE(provinces.name, '-')",
            'region' => "COALESCE(regions.name, 'Belum dipetakan')",
            'urusan' => "COALESCE(pmo_urusans.urusan, '-')",
        ];
    }

    protected function metrics(): array
    {
        return ['opds' => 'COUNT(DISTINCT pmo_opds.id)'];
    }

    protected function filters(): array
    {
        return [
            'name' => 'pmo_opds.name',
            'jenis' => "COALESCE(pmo_opds.jenis, '')",
            'province' => "COALESCE(provinces.name, '')",
            'regency' => "COALESCE(regencies.name, '')",
            'region' => "COALESCE(regions.name, '')",
            'urusan' => ["COALESCE(pmo_urusans.urusan, '')", "COALESCE(pmo_urusans.code, '')"],
        ];
    }

    protected function groupSizeExpression(): string
    {
        return 'pmo_opds.id';
    }
}
