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
        Schema::dropIfExists('investor_dividends');
        Schema::dropIfExists('disputed_document_logs');
        Schema::dropIfExists('ai_command_logs');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('stage_validation_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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

        Schema::create('ai_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('raw_prompt');
            $table->json('interpreted_data')->nullable();
            $table->json('executed_actions')->nullable();
            $table->enum('status', ['Success', 'Failed', 'Needs_Review'])->default('Success');
            $table->timestamps();
        });

        Schema::create('disputed_document_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('rejected_by')->constrained('users')->cascadeOnDelete();
            $table->text('rejection_reason');
            $table->string('rejected_file_path')->nullable();
            $table->string('new_file_path')->nullable();
            $table->timestamps();
        });

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
    }
};
