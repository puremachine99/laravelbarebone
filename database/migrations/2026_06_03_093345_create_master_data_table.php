<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. regions
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // "Region 1", "Region Jawa Timur", dll
            $table->string('code', 20)->unique()->nullable(); // opsional: "REG-01"
            $table->timestamps();
        });

        // 2. provinces
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('name', 150)->unique();
            $table->string('code', 10)->unique()->nullable(); // kode lama: "11", "12", dll
            $table->timestamps();
        });

        // 3. regencies
        Schema::create('regencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 10)->unique()->nullable(); // kode lama dari sistem existing
            $table->string('type', 20)->default('Kabupaten'); // Kabupaten / Kota
            $table->timestamps();
        });

        // 4. OPDs
        Schema::create('opds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regency_id')->constrained('regencies')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // 5. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // 6. Positions
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('type', 50)->default('Fungsional');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opds');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('positions');
    }
};
