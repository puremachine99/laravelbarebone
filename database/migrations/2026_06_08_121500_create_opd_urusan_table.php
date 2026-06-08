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
        Schema::create('opd_urusan', function (Blueprint $table) {
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('urusan_id')->constrained('urusans')->cascadeOnDelete();
            $table->primary(['opd_id', 'urusan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opd_urusan');
    }
};
