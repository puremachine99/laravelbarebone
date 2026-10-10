<?php

namespace App\Services\AiData\Datasets;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Departments with their number of active employees.
 */
class DepartmentsDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('departments')
            ->leftJoin('employees', fn (JoinClause $join) => $join->on('employees.department_id', '=', 'departments.id')
                ->whereNull('employees.deleted_at')
                ->where('employees.status', EmployeeStatus::Active->value));
    }

    protected function dimensions(): array
    {
        return [
            'department' => 'departments.name',
            'code' => "COALESCE(departments.code, '-')",
        ];
    }

    protected function metrics(): array
    {
        return ['employees' => 'COUNT(DISTINCT employees.id)'];
    }

    protected function filters(): array
    {
        return ['name' => ['departments.name', "COALESCE(departments.code, '')"]];
    }

    protected function groupSizeExpression(): string
    {
        return 'departments.id';
    }
}
