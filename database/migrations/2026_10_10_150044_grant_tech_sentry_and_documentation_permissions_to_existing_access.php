<?php

use App\Support\ResourcePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Sentry and API Documentation moved from ad hoc gates (a hardcoded Super Admin role check, and an
     * ungated sidebar link) into the Technology module's resource permissions. Whoever already holds
     * `access-technology` keeps seeing both menu items, same as when the Timeline Calculator permission
     * was introduced.
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
