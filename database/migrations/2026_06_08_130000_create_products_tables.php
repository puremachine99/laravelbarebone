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
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('service_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('full_code', 50)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('service_center_id')->constrained('service_centers')->cascadeOnDelete();
            $table->string('category_code', 50)->constrained('product_categories', 'code');
            $table->string('output_name', 255);
            $table->string('pelaksana', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sequence');
            $table->string('generated_code', 100)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outputs');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('service_centers');
        Schema::dropIfExists('divisions');
    }
};
