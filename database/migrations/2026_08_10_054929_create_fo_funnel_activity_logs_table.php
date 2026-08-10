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
        Schema::create('fo_funnel_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fo_funnel_activity_id')->constrained('fo_funnel_activities')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('old_tanggal')->nullable();
            $table->date('new_tanggal')->nullable();
            $table->text('old_catatan')->nullable();
            $table->text('new_catatan')->nullable();
            $table->timestamps();

            $table->index('fo_funnel_activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fo_funnel_activity_logs');
    }
};
