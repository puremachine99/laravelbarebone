<?php

namespace App\Support;

class PersonName
{
    /**
     * Keeps the first $words words of a name: "Budi Santoso Wijaya" becomes "Budi Santoso".
     */
    public static function short(?string $name, int $words = 2): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_slice($parts, 0, $words));
    }
}
