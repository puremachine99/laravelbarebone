<?php

use App\Support\ResourcePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Existing roles/users keep their current access: whoever holds a module permission gets every
     * action permission of that module (Direksi: view only).
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ResourcePermissions::grantLegacyAccess();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Granted permissions are intentionally left in place.
    }
};
