<?php

namespace App\Services\AiData\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Product master (outputs): name, generated code, category, service center, division, pelaksana and active status.
 */
class ProductsDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('pmo_outputs')
            ->leftJoin('pmo_product_categories', 'pmo_product_categories.code', '=', 'pmo_outputs.category_code')
            ->leftJoin('pmo_service_centers', 'pmo_service_centers.id', '=', 'pmo_outputs.service_center_id')
            ->leftJoin('pmo_divisions', 'pmo_divisions.id', '=', 'pmo_outputs.division_id');
    }

    protected function dimensions(): array
    {
        return [
            'product' => 'pmo_outputs.output_name',
            'code' => "COALESCE(pmo_outputs.generated_code, '-')",
            'category' => "COALESCE(pmo_product_categories.name, pmo_outputs.category_code, '-')",
            'service_center' => "COALESCE(pmo_service_centers.name, '-')",
            'division' => "COALESCE(pmo_divisions.name, '-')",
            'pelaksana' => "COALESCE(pmo_outputs.pelaksana, '-')",
            'status' => "CASE WHEN pmo_outputs.status THEN 'Aktif' ELSE 'Nonaktif' END",
        ];
    }

    protected function metrics(): array
    {
        return ['products' => 'COUNT(DISTINCT pmo_outputs.id)'];
    }

    protected function filters(): array
    {
        return [
            'name' => ['pmo_outputs.output_name', "COALESCE(pmo_outputs.generated_code, '')"],
            'category' => ["COALESCE(pmo_product_categories.name, '')", "COALESCE(pmo_outputs.category_code, '')"],
            'service_center' => "COALESCE(pmo_service_centers.name, '')",
            'division' => "COALESCE(pmo_divisions.name, '')",
            'pelaksana' => "COALESCE(pmo_outputs.pelaksana, '')",
        ];
    }

    protected function groupSizeExpression(): string
    {
        return 'pmo_outputs.id';
    }
}
