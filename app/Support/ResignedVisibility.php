<?php

namespace App\Support;

/**
 * Per-module switch for showing resigned/ex-employees in that module's pickers and team totals.
 *
 * The switch is flipped from one place per module (e.g. FO Management for the investors module)
 * and every other page of that module follows it. It lives in the admin's own session, so
 * flipping it never changes what other users see.
 */
class ResignedVisibility
{
    /**
     * Roles allowed to see resigned employees, per module.
     *
     * @var array<string, array<int, string>>
     */
    private const MODULE_ROLES = [
        'investors' => ['Investor Admin', 'Super Admin', 'Direksi'],
    ];

    public static function canToggle(string $module): bool
    {
        return (bool) auth()->user()?->hasRole(self::MODULE_ROLES[$module] ?? []);
    }

    public static function enabled(string $module): bool
    {
        return self::canToggle($module) && session()->get(self::key($module), false);
    }

    public static function set(string $module, bool $show): void
    {
        if (! self::canToggle($module)) {
            return;
        }

        session()->put(self::key($module), $show);
    }

    private static function key(string $module): string
    {
        return "show-resigned.{$module}";
    }
}
