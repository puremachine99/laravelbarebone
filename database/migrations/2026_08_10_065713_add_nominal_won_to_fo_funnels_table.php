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
            $table->decimal('nominal_won', 15, 2)->nullable()->after('tgl_won');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->dropColumn('nominal_won');
        });
    }
};
