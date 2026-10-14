<?php

use App\Support\ResourcePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the new "tech-sso.view" permission so it shows up in the role matrix. Nobody is granted it
     * automatically (Super Admin passes via Gate::before): an admin ticks it per role.
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
