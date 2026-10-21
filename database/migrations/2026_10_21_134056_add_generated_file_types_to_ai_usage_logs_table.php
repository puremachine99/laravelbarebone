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
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            // File types the AI generated in the answer, e.g. ["xlsx","jpeg"]; never names or content.
            $table->json('generated_file_types')->nullable()->after('finish_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn('generated_file_types');
        });
    }
};
