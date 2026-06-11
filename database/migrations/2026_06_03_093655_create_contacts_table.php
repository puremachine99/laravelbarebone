<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('sumber', [
                'SDS Umum',
                'SDS Wilayah',
                'Referal',
                'Visit/ Pitching',
                'Media Sosial Instansi',
                'Customer Service',
            ]);
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('pic_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('category', [
                'Kementerian / Lembaga',
                'Pemerintah Daerah',
                'Non Pemerintah',
            ]);
            $table->foreignId('regency_id')->nullable()->constrained('regencies')->nullOnDelete();
            $table->string('jabatan')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
