<?php

namespace Database\Seeders;

use App\Models\User;
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

        // Create permissions
        $permissions = [
            'manage-internal-org',
            'manage-partnership',
            'manage-products',
            'manage-investors',
            'view-executive-dashboard',
            'manage-talent-okr',
            'manage-pipeline',
            'manage-finance-intake',
            'manage-finance-reconciliation',
            'manage-finance-disbursements',
            'view-finance-clearance',
            'manage-chat',
            'manage-ai-center',
            'modify-master-data',
            'verify-manager',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create roles and assign permissions
        $superAdminRole = Role::findOrCreate('Super Admin');
        $superAdminRole->givePermissionTo(Permission::all());

        $internalOrgAdminRole = Role::findOrCreate('Internal Org Admin');
        $internalOrgAdminRole->givePermissionTo('manage-internal-org');

        $partnershipAdminRole = Role::findOrCreate('Partnership Admin');
        $partnershipAdminRole->givePermissionTo(['manage-partnership', 'manage-pipeline']);

        $pmoRole = Role::findOrCreate('PMO');
        $pmoRole->givePermissionTo('manage-pipeline');

        $productAdminRole = Role::findOrCreate('Product Admin');
        $productAdminRole->givePermissionTo('manage-products');

        $investorAdminRole = Role::findOrCreate('Investor Admin');
        $investorAdminRole->givePermissionTo('manage-investors');

        // Assign Super Admin role to the default admin user
        $superAdmin = User::where('email', 'supernakama@smartid.co.id')->first();
        if ($superAdmin) {
            $superAdmin->assignRole($superAdminRole);
        }
    }
}
