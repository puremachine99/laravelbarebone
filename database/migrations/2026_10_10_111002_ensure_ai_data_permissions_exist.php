<?php

use App\Support\ResourcePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the new "ai-data-*" permissions so they show up in the role matrix. Nobody is granted them
     * automatically: an admin ticks them per role.
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
