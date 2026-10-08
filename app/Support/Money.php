<?php

namespace App\Support;

class Money
{
    /**
     * Strip thousand separators / stray characters from a masked currency input
     * (e.g. "250.000.000") back into a clean number the database can store.
     *
     * The masked inputs always use "." as the thousands separator and "," as the
     * decimal separator (see x-mask:dynamic="$money($input, ',', '.', 0)"). Stripping
     * every non-digit blindly used to concatenate the decimal part into the integer —
     * a stray ",00" surviving from the input mask turned "500.000.000,00" into the
     * digit string "50000000000" (Rp 500 juta saved as Rp 50 M). Dropping everything
     * from the first "," onward before stripping keeps that from happening; whole
     * rupiah amounts have no legitimate use for the fractional part anyway.
     */
    public static function normalize(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $isNegative = str_starts_with(ltrim($value), '-');
        $withoutThousands = str_replace('.', '', $value);
        [$integerPart] = explode(',', $withoutThousands, 2);
        $digits = preg_replace('/[^0-9]/', '', $integerPart);

        if ($digits === '') {
            return null;
        }

        return $isNegative ? -(float) $digits : (float) $digits;
    }

    /**
     * Short display form with two decimals, truncated rather than rounded so the figure
     * never overstates the real value: Rp 0, Rp 3,50 Jt, Rp 3,57 M.
     */
    public static function abbreviate(mixed $value): string
    {
        $value = (int) $value;

        if (! $value) {
            return 'Rp 0';
        }

        if (abs($value) >= 1_000_000_000) {
            return 'Rp '.number_format(intdiv($value, 10_000_000) / 100, 2, ',', '.').' M';
        }

        return 'Rp '.number_format(intdiv($value, 10_000) / 100, 2, ',', '.').' Jt';
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
