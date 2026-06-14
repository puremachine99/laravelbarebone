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
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('opd_id');
            $table->index('regency_id');
            $table->index('region_id');
        });

        Schema::table('opds', function (Blueprint $table) {
            $table->index('regency_id');
        });

        Schema::table('regencies', function (Blueprint $table) {
            $table->index('province_id');
            $table->index('region_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('position_struktural_id');
            $table->index('position_fungsional_id');
            $table->index('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['position_struktural_id']);
            $table->dropIndex(['position_fungsional_id']);
            $table->dropIndex(['region_id']);
        });

        Schema::table('regencies', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
            $table->dropIndex(['region_id']);
        });

        Schema::table('opds', function (Blueprint $table) {
            $table->dropIndex(['regency_id']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['opd_id']);
            $table->dropIndex(['regency_id']);
            $table->dropIndex(['region_id']);
        });
    }
};
