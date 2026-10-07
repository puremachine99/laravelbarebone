<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pmo_opds', function (Blueprint $table) {
            $table->string('jenis', 50)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pmo_opds', function (Blueprint $table) {
            $table->dropIndex(['jenis']);
            $table->dropColumn('jenis');
        });
    }
};
