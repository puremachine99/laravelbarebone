<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Reads config/resource_permissions.php and keeps the matching Spatie permissions in sync.
 */
class ResourcePermissions
{
    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return config('resource_permissions.actions');
    }

    /**
     * @return array<string, array{label: string, module: string, resources: array<string, array{label: string, actions: array<int, string>}>}>
     */
    public static function groups(): array
    {
        return config('resource_permissions.groups');
    }

    /**
     * @return array<int, string>
     */
    public static function names(?string $modulePermission = null): array
    {
        return collect(self::groups())
            ->when($modulePermission, fn ($groups) => $groups->where('module', $modulePermission))
            ->flatMap(fn (array $group) => collect($group['resources'])
                ->flatMap(fn (array $resource, string $key) => array_map(fn (string $action) => "{$key}.{$action}", $resource['actions'])))
            ->values()
            ->all();
    }

    /**
     * Human label for a "{resource}.{action}" permission, or null if it is not in the catalog.
     */
    public static function label(string $permission): ?string
    {
        [$resource, $action] = array_pad(explode('.', $permission, 2), 2, null);

        foreach (self::groups() as $group) {
            if (isset($group['resources'][$resource]) && in_array($action, $group['resources'][$resource]['actions'], true)) {
                return $group['resources'][$resource]['label'].': '.self::actionLabels()[$action];
            }
        }

        return null;
    }

    /**
     * Make sure a set of ticked permissions is coherent: any action on a resource implies "view" on it,
     * and any resource permission implies its module's "access-*" permission (otherwise the role could
     * hold actions it can never reach).
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function normalize(array $permissions): array
    {
        $catalog = self::names();

        $normalized = collect($permissions)
            ->filter(fn (string $name) => ! str_contains($name, '.') || in_array($name, $catalog, true));

        foreach (self::groups() as $group) {
            foreach ($group['resources'] as $key => $resource) {
                $ticked = $normalized->contains(fn (string $name) => str_starts_with($name, "{$key}."));

                if ($ticked) {
                    $normalized->push("{$key}.view", $group['module']);
                }
            }
        }

        return $normalized->unique()->values()->all();
    }

    /**
     * Create every catalog permission that does not exist yet.
     */
    public static function ensureExist(): void
    {
        foreach (self::names() as $name) {
            Permission::findOrCreate($name);
        }
    }

    /**
     * One-off migration of existing access: every role or user that already holds a module permission
     * keeps doing everything it could do before (Direksi stays view-only, and resources that used to
     * be write-protected by another permission only hand out their write actions to its holders). Groups marked
     * `legacy_grant => false` are skipped: their permissions are only ever ticked by hand.
     */
    public static function grantLegacyAccess(): void
    {
        self::ensureExist();

        foreach (self::groups() as $group) {
            if (($group['legacy_grant'] ?? true) === false) {
                continue;
            }

            $modulePermission = Permission::where('name', $group['module'])->first();

            if (! $modulePermission) {
                continue;
            }

            foreach ($modulePermission->roles as $role) {
                $role->givePermissionTo(self::legacyNamesFor($group, fn (string $permission) => $role->permissions->contains('name', $permission), $role->name === 'Direksi'));
            }

            foreach ($modulePermission->users as $user) {
                $user->givePermissionTo(self::legacyNamesFor($group, fn (string $permission) => $user->hasDirectPermission($permission), false));
            }
        }
    }

    /**
     * @param  array{module: string, resources: array<string, array{label: string, actions: array<int, string>, legacy_write_permission?: string}>}  $group
     * @param  callable(string): bool  $holds
     * @return array<int, string>
     */
    private static function legacyNamesFor(array $group, callable $holds, bool $viewOnly): array
    {
        $names = [];

        foreach ($group['resources'] as $key => $resource) {
            $writeGate = $resource['legacy_write_permission'] ?? null;

            foreach ($resource['actions'] as $action) {
                $isView = $action === 'view';

                if (! $isView && ($viewOnly || ($writeGate && ! $holds($writeGate)))) {
                    continue;
                }

                $names[] = "{$key}.{$action}";
            }
        }

        return $names;
    }
}
