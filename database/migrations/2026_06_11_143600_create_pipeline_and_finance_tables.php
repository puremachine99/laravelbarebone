<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pipeline_stages
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pipeline_name');
            $table->string('stage_name');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. stage_validation_rules
        Schema::create('stage_validation_rules', function (Blueprint $table) {
            $table->uuid('stage_id')->primary();
            $table->foreign('stage_id')->references('id')->on('pipeline_stages')->cascadeOnDelete();
            $table->json('authorized_roles')->nullable();
            $table->boolean('require_notes')->default(false);
            $table->boolean('require_file')->default(false);
            $table->json('allowed_file_types')->nullable();
            $table->boolean('require_nominal')->default(false);
            $table->boolean('require_contact')->default(false);
            $table->timestamps();
        });

        // 3. cards
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->uuid('stage_id');
            $table->foreign('stage_id')->references('id')->on('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->decimal('nominal_value', 15, 2)->default(0.00);
            $table->string('s3_document_path')->nullable();
            $table->enum('status', ['Active', 'Disputed', 'Re-Submitted', 'Completed'])->default('Active');
            $table->timestamps();
        });

        // 4. finance_transactions
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

        // 5. disputed_document_logs
        Schema::create('disputed_document_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('rejected_by')->constrained('users')->cascadeOnDelete();
            $table->text('rejection_reason');
            $table->string('rejected_file_path')->nullable();
            $table->string('new_file_path')->nullable();
            $table->timestamps();
        });

        // 6. investor_dividends
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

        // 7. ai_command_logs
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
    }
};
