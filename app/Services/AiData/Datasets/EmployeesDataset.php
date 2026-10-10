<?php

namespace App\Services\AiData\Datasets;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Active employees: name, NIS (employee ID), department, positions and region only. NIK (KTP), NPWP, employment
 * status, marital status, birth date, gender, phone and contract data are never selected. Offboarded people
 * (resign/PHK/...) are left out without revealing why.
 */
class EmployeesDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('positions AS struktural', 'struktural.id', '=', 'employees.position_struktural_id')
            ->leftJoin('positions AS fungsional', 'fungsional.id', '=', 'employees.position_fungsional_id')
            ->leftJoin('positions AS teknis', 'teknis.id', '=', 'employees.position_teknis_id')
            ->leftJoin('regions', 'regions.id', '=', 'employees.region_id')
            ->whereNull('employees.deleted_at')
            ->whereNull('users.deleted_at')
            ->where('employees.status', EmployeeStatus::Active->value);
    }

    protected function dimensions(): array
    {
        return [
            'employee' => 'users.name',
            'nis' => "COALESCE(employees.nis, '-')",
            'department' => "COALESCE(departments.name, 'Tanpa departemen')",
            'position_struktural' => "COALESCE(struktural.name, '-')",
            'position_fungsional' => "COALESCE(fungsional.name, '-')",
            'position_teknis' => "COALESCE(teknis.name, '-')",
            'region' => "COALESCE(regions.name, '-')",
        ];
    }

    protected function metrics(): array
    {
        return ['employees' => 'COUNT(DISTINCT employees.id)'];
    }

    protected function filters(): array
    {
        return [
            'name' => 'users.name',
            'position' => ["COALESCE(struktural.name, '')", "COALESCE(fungsional.name, '')", "COALESCE(teknis.name, '')"],
            'region' => "COALESCE(regions.name, '')",
        ];
    }

    protected function groupSizeExpression(): string
    {
        return 'employees.id';
    }

    protected function departmentColumn(): ?string
    {
        return 'employees.department_id';
    }
}
