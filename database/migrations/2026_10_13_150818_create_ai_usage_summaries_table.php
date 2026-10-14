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
        Schema::create('ai_usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('ok_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('denied_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('cost_usd', 14, 6)->nullable();
            $table->unsignedInteger('avg_first_token_ms')->nullable();
            $table->unsignedInteger('avg_duration_ms')->nullable();
            $table->json('breakdown');
            $table->timestamps();

            $table->unique(['period', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_summaries');
    }
};
