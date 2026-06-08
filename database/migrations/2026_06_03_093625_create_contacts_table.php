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
            $table->enum('category', ['Pemerintah', 'Swasta', 'Instruktur']);

            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('regency_id')->nullable()->constrained('regencies')->nullOnDelete();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();

            $table->string('pic_name', 150);
            $table->string('pic_email', 150)->unique();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
