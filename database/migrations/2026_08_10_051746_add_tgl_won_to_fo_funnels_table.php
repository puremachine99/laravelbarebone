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
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->date('tgl_won')->nullable()->after('proyeksi_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->dropColumn('tgl_won');
        });
    }
};
