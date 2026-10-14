<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passport only keeps a bcrypt hash of the client secret. This table keeps a copy encrypted
     * with APP_KEY so admins can reveal it again from /tech/sso instead of regenerating it.
     */
    public function up(): void
    {
        Schema::create('oauth_client_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->unique()->constrained('oauth_clients')->cascadeOnDelete();
            $table->text('secret');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_secrets');
    }
};
