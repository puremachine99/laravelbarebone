<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add region_id to regencies as nullable
        Schema::table('regencies', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
        });

        // 2. Data migration: copy region_id from provinces to regencies using correlated subquery
        DB::statement('UPDATE regencies SET region_id = (SELECT region_id FROM provinces WHERE provinces.id = regencies.province_id)');

        // 3. Drop region_id foreign key and column from provinces
        Schema::table('provinces', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add region_id back to provinces (nullable first, then we can populate it)
        Schema::table('provinces', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions')->cascadeOnDelete();
        });

        // 2. Data migration: copy region_id back to provinces from their child regencies
        // Since different regencies in the same province could theoretically have different region_ids now,
        // we'll just take the first non-null region_id from any child regency.
        DB::statement('UPDATE provinces SET region_id = (SELECT region_id FROM regencies WHERE regencies.province_id = provinces.id AND regencies.region_id IS NOT NULL LIMIT 1)');

        // 3. Drop region_id from regencies
        Schema::table('regencies', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });
    }
};
