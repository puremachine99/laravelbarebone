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
        // Superseded by the generic polymorphic `activity_logs` table — target change history
        // is now recorded there (loggable_type = FoTarget) instead of this dedicated table.
        Schema::dropIfExists('fo_target_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('fo_target_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('metric_key', 30);
            $table->decimal('old_value', 15, 2)->nullable();
            $table->decimal('new_value', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'year']);
        });
    }
};
