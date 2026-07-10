<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pipeline_profiles
        Schema::create('pipeline_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pipeline_name'); // e.g. "Pitching", "Sales"
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 2. pipeline_stages
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_profile_id')->constrained('pipeline_profiles')->cascadeOnDelete();
            $table->string('stage_name');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. cards
        Schema::create('cards', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('title');
            $table->enum('status', ['Active', 'Disputed', 'Re-Submitted', 'Completed', 'won', 'hot', 'warm', 'lost'])->default('Active');

            // Relations
            $table->foreignId('stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('output_id')->nullable()->constrained('outputs')->nullOnDelete();

            // Financial
            $table->decimal('nominal_value', 15, 2)->default(0.00);
            $table->integer('fiscal_year')->default((int) date('Y'));

            // Pitching info
            $table->string('sumber_proyeksi')->nullable();
            $table->text('detail_sumber_proyeksi')->nullable();
            $table->date('pitching_date')->nullable();
            $table->string('communication_method')->nullable();

            // Projection & outcome
            $table->string('projection_month')->nullable();
            $table->string('projection_week')->nullable();
            $table->string('projection_status')->nullable();
            $table->string('drive_link')->nullable();
            $table->string('negative_reason')->nullable();

            // Verification
            $table->boolean('manager_verified')->default(false);

            // Documents
            $table->string('s3_document_path')->nullable();

            // Audit & tracking
            $table->json('history_logs')->nullable();
            $table->json('follow_ups')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipeline_profiles');
    }
};
