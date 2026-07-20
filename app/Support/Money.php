<?php

namespace App\Support;

class Money
{
    /**
     * Strip thousand separators / stray characters from a masked currency input
     * (e.g. "250.000.000") back into a clean number the database can store.
     */
    public static function normalize(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $isNegative = str_starts_with(ltrim($value), '-');
        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        return $isNegative ? -(float) $digits : (float) $digits;
    }

    /**
     * Short display form: Rp 0, Rp 3,5 Jt, Rp 3,5 M.
     */
    public static function abbreviate(mixed $value): string
    {
        $value = (float) $value;

        if (! $value) {
            return 'Rp 0';
        }

        if ($value >= 1_000_000_000) {
            return 'Rp '.rtrim(rtrim(number_format($value / 1_000_000_000, 1, ',', '.'), '0'), ',').' M';
        }

        return 'Rp '.rtrim(rtrim(number_format($value / 1_000_000, 1, ',', '.'), '0'), ',').' Jt';
    }

    /**
     * Full, non-abbreviated display form: Rp 3.500.000.000 — used for the tooltip
     * that reveals the exact value behind an abbreviated number.
     */
    public static function exact(mixed $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
