<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SSO settings managed from /tech/sso on top of Passport's own client columns.
     * `scopes` is read by Passport itself (Client::hasScope): null means every scope is allowed.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->json('scopes')->nullable()->after('grant_types');
            $table->boolean('skip_authorization')->default(false)->after('scopes');
            $table->json('allowed_roles')->nullable()->after('skip_authorization');
            $table->json('allowed_department_ids')->nullable()->after('allowed_roles');
            $table->timestamp('last_used_at')->nullable()->after('revoked');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['description', 'scopes', 'skip_authorization', 'allowed_roles', 'allowed_department_ids', 'last_used_at']);
        });
    }
};
