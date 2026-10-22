<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * External websites/apps allowed to call the public lookup API (/api/opds, /api/regencies, ...). Only the SHA-256
     * of the token is stored; the plain token is shown once when issued.
     */
    public function up(): void
    {
        Schema::create('api_consumers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->json('allowed_origins');
            $table->boolean('allow_server_requests')->default(false);
            $table->unsignedInteger('rate_limit_per_minute')->default(120);
            $table->text('notes')->nullable();
            $table->string('token_prefix', 20);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_origin')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_consumers');
    }
};
