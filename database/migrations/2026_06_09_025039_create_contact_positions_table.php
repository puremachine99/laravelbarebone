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
        Schema::create('contact_positions', function (Blueprint $table) {
            $table->id();
            $table->string('kelompok_jabatan')->nullable();
            $table->string('eselon')->nullable();
            $table->string('name')->unique();
            $table->string('tingkat')->nullable();
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('contact_position_id')->nullable()->after('regency_id')->constrained('contact_positions')->nullOnDelete();
            $table->dropColumn('jabatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['contact_position_id']);
            $table->dropColumn('contact_position_id');
            $table->string('jabatan')->nullable();
        });

        Schema::dropIfExists('contact_positions');
    }
};
