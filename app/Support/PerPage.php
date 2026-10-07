<?php

namespace App\Support;

class PerPage
{
    public const DEFAULT = 10;

    /**
     * Page sizes offered by every table. "Show all" is intentionally absent.
     *
     * @var list<int>
     */
    public const OPTIONS = [10, 30, 50, 100];

    /**
     * Coerce user input to an allowed page size so tampered values cannot request an unbounded page.
     */
    public static function resolve(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::OPTIONS, true) ? $value : self::DEFAULT;
    }
}
