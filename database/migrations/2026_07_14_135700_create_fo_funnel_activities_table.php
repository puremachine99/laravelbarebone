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
        Schema::create('fo_funnel_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fo_funnel_id')->constrained('fo_funnels')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('stage', 20);
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('fo_funnel_id');
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fo_funnel_activities');
    }
};
