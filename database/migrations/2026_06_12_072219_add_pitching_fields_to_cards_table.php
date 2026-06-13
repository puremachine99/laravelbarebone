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
        Schema::table('cards', function (Blueprint $table) {
            $table->string('sumber_proyeksi')->nullable();
            $table->text('detail_sumber_proyeksi')->nullable();
            $table->date('pitching_date')->nullable();
            $table->string('communication_method')->nullable();
            $table->integer('fiscal_year')->default((int) date('Y'));
            $table->timestamp('archived_at')->nullable();
            $table->string('negative_reason')->nullable();
            $table->string('projection_month')->nullable();
            $table->string('projection_week')->nullable();
            $table->string('projection_status')->nullable();
            $table->string('drive_link')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'sumber_proyeksi',
                'detail_sumber_proyeksi',
                'pitching_date',
                'communication_method',
                'fiscal_year',
                'archived_at',
                'negative_reason',
                'projection_month',
                'projection_week',
                'projection_status',
                'drive_link',
            ]);
        });
    }
};
