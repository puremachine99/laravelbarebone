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

        // 3. stage_validation_rules
        Schema::create('stage_validation_rules', function (Blueprint $table) {
            $table->foreignId('stage_id')->primary()->constrained('pipeline_stages')->cascadeOnDelete();
            $table->json('authorized_roles')->nullable();
            $table->boolean('require_notes')->default(false);
            $table->boolean('require_file')->default(false);
            $table->json('allowed_file_types')->nullable();
            $table->boolean('require_nominal')->default(false);
            $table->boolean('require_contact')->default(false);
            $table->timestamps();
        });

        // 4. cards
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

        // 5. finance_transactions
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->decimal('gross_value', 15, 2)->default(0.00);
            $table->string('tax_type')->nullable();
            $table->decimal('tax_nominal', 15, 2)->default(0.00);
            $table->json('vendor_allocations')->nullable();
            $table->decimal('net_value', 15, 2)->default(0.00);
            $table->json('payment_terms')->nullable();
            $table->timestamps();
        });

        // 6. disputed_document_logs
        Schema::create('disputed_document_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('rejected_by')->constrained('users')->cascadeOnDelete();
            $table->text('rejection_reason');
            $table->string('rejected_file_path')->nullable();
            $table->string('new_file_path')->nullable();
            $table->timestamps();
        });

        // 7. investor_dividends
        Schema::create('investor_dividends', function (Blueprint $table) {
            $table->id();
            $table->string('investor_name');
            $table->decimal('capital_amount', 15, 2)->default(0.00);
            $table->decimal('share_percentage', 5, 2)->default(0.00);
            $table->foreignId('transaction_id')->constrained('finance_transactions')->cascadeOnDelete();
            $table->decimal('dividend_paid', 15, 2)->default(0.00);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 8. ai_command_logs
        Schema::create('ai_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('raw_prompt');
            $table->json('interpreted_data')->nullable();
            $table->json('executed_actions')->nullable();
            $table->enum('status', ['Success', 'Failed', 'Needs_Review'])->default('Success');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_command_logs');
        Schema::dropIfExists('investor_dividends');
        Schema::dropIfExists('disputed_document_logs');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('stage_validation_rules');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipeline_profiles');
    }
};
