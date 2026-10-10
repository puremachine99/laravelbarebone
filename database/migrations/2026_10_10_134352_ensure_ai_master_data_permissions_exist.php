<?php

use App\Support\ResourcePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the "ai-data-*" permissions for the master data datasets (employees, departments, roles, regions, OPD,
     * urusan, products) so they show up in the role matrix. Nobody is granted them automatically.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ResourcePermissions::ensureExist();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are intentionally left in place.
    }
};
