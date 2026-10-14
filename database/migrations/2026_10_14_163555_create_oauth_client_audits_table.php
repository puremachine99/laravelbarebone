<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what to an SSO client (created, secret revealed/regenerated, tokens revoked, ...).
     * Separate from activity_logs because OAuth client ids are UUIDs.
     */
    public function up(): void
    {
        Schema::create('oauth_client_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->index();
            $table->string('client_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_audits');
    }
};
