<?php

namespace App\Support\CsvExport;

class CsvExportRegistry
{
    /** @var array<string, CsvExportDefinition> */
    protected static array $definitions = [];

    public static function register(CsvExportDefinition $definition): void
    {
        static::$definitions[$definition->slug] = $definition;
    }

    public static function find(string $slug): ?CsvExportDefinition
    {
        return static::$definitions[$slug] ?? null;
    }

    public static function forRouteName(?string $routeName): ?CsvExportDefinition
    {
        if (! $routeName) {
            return null;
        }

        foreach (static::$definitions as $definition) {
            if ($definition->routeName === $routeName) {
                return $definition;
            }
        }

        return null;
    }
}
