<?php

namespace App\Services\AiData;

use Illuminate\Support\Facades\DB;

/**
 * "YYYY-MM" of a date column, for every database driver this app runs on (pgsql in production, sqlite in tests).
 */
class SqlMonth
{
    public static function of(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
