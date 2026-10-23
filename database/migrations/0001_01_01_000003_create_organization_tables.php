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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 3)->nullable();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('department_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('module_code');
            $table->timestamps();

            $table->unique(['department_id', 'module_code']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('type', 50)->default('Fungsional');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->index()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_struktural_id')->nullable()->index()->constrained('positions')->nullOnDelete();
            $table->foreignId('position_fungsional_id')->nullable()->index()->constrained('positions')->nullOnDelete();
            $table->foreignId('position_teknis_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('employment_status', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->date('status_date')->nullable();
            $table->text('status_reason')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('department_module');
        Schema::dropIfExists('departments');
    }
};
