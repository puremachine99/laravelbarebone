<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('position_struktural_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignUuid('position_fungsional_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('employment_status', 100)->nullable();
            $table->boolean('is_cltp')->default(false);
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('nis', 50)->nullable();
            $table->string('nik', 16)->unique()->nullable();
            $table->string('npwp', 50)->unique()->nullable();
            $table->string('education_level', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->text('target_okr')->nullable();
            $table->jsonb('pipeline_permissions')->nullable();
            $table->decimal('kpi_achieved_m1', 5, 2)->default(0.00);
            $table->decimal('kpi_achieved_m2', 5, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
