<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_data_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dataset', 100);
            $table->string('outcome', 20);
            $table->json('parameters');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('suppressed_rows')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['dataset', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_data_query_logs');
    }
};
