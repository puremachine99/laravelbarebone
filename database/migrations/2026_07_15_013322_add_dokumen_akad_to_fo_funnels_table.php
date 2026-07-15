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
        Schema::table('fo_funnels', function (Blueprint $table) {
            // Dokumen akad diunggah ke bucket S3 (lihat config/filesystems.php, disk "s3") — path adalah key objek di bucket.
            $table->string('dokumen_akad_path')->nullable()->after('tier');
            $table->string('dokumen_akad_nama')->nullable()->after('dokumen_akad_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->dropColumn(['dokumen_akad_path', 'dokumen_akad_nama']);
        });
    }
};
