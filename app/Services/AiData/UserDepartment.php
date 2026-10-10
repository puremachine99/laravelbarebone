<?php

namespace App\Services\AiData;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * A user's department for datasets built on `users`: the employee record is the source of truth, users.department_id
 * (a synced copy that can lag behind) is only the fallback.
 */
class UserDepartment
{
    /**
     * SQL for the department id, after join().
     */
    public const ID = 'COALESCE(user_employee.department_id, users.department_id)';

    /**
     * Joins the user's (non-deleted) employee record and their department as `departments`.
     */
    public static function join(Builder $query): Builder
    {
        return $query
            ->leftJoin('employees AS user_employee', fn (JoinClause $join) => $join->on('user_employee.user_id', '=', 'users.id')
                ->whereNull('user_employee.deleted_at'))
            ->leftJoin('departments', 'departments.id', '=', DB::raw(self::ID));
    }
}
