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
        Schema::create('ai_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('remote_file_id', 64)->unique();
            $table->string('origin', 20);
            $table->string('name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'origin', 'ai_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_files');
    }
};
