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
        Schema::create('fo_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fo_contact_id')->constrained('fo_contacts')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('stage', 20)->default('leads');
            $table->string('sumber');

            // Leads / QL
            $table->date('tgl_leads')->nullable();
            $table->date('tgl_qualified')->nullable();

            // Projection
            $table->date('proyeksi_close_date')->nullable();
            $table->decimal('proyeksi_amount', 15, 2)->nullable();

            // Closing outcome — set once when the funnel ends up won/lost
            $table->string('status_closing', 20)->nullable();
            $table->date('tgl_closing')->nullable();
            $table->decimal('nominal_investasi', 15, 2)->nullable();
            $table->string('jangka_waktu')->nullable();
            $table->string('bagi_hasil')->nullable();
            $table->string('tier')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('fo_contact_id');
            $table->index('employee_id');
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fo_funnels');
    }
};
