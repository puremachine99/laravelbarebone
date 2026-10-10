<?php

namespace App\Services\AiData\Datasets;

use App\Models\User;
use App\Services\AiData\UserDepartment;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Application roles with how many (non-deleted) users hold them, optionally per department. Role names and counts
 * only: no user names and no permission details.
 */
class RolesDataset extends MasterDataset
{
    protected function query(): Builder
    {
        return UserDepartment::join(DB::table('roles')
            ->leftJoin('model_has_roles', fn (JoinClause $join) => $join->on('model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass()))
            ->leftJoin('users', fn (JoinClause $join) => $join->on('users.id', '=', 'model_has_roles.model_id')
                ->whereNull('users.deleted_at')));
    }

    protected function dimensions(): array
    {
        return [
            'role' => 'roles.name',
            'department' => "COALESCE(departments.name, 'Tanpa departemen')",
        ];
    }

    protected function metrics(): array
    {
        return ['users' => 'COUNT(DISTINCT users.id)'];
    }

    protected function filters(): array
    {
        return [
            'role' => 'roles.name',
            'department' => "COALESCE(departments.name, '')",
        ];
    }

    protected function groupSizeExpression(): string
    {
        return 'roles.id';
    }
}
