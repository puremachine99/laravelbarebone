<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\ResourcePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Module-level permissions (one per business module, mirrors config('modules.list'))
        $modulePermissions = collect(config('modules.list'))->pluck('permission')->all();

        // Cross-cutting permissions not tied to a single department/module
        $otherPermissions = [
            'modify-master-data',
        ];

        foreach ([...$modulePermissions, ...$otherPermissions] as $permission) {
            Permission::findOrCreate($permission);
        }

        ResourcePermissions::ensureExist();

        // Create roles and assign permissions
        $superAdminRole = Role::findOrCreate('Super Admin');
        $superAdminRole->givePermissionTo(Permission::all());

        $internalOrgAdminRole = Role::findOrCreate('Internal Org Admin');
        $internalOrgAdminRole->givePermissionTo('access-internal-org');

        $technologyRole = Role::findOrCreate('Technology');
        $technologyRole->givePermissionTo('access-technology');

        // Default roles get every action of the modules they can enter.
        ResourcePermissions::grantLegacyAccess();

        // Assign Super Admin role to the default admin user
        $superAdmin = User::where('email', 'admin@example.com')->first();
        if ($superAdmin) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}
