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
            // Menampung banyak dokumen akad ber-tag ("Perjanjian A", dst), tiap entri
            // [tag, path, nama] — path adalah key objek di bucket S3/Minio.
            // dokumen_akad_path/dokumen_akad_nama tetap dipertahankan untuk data lama.
            $table->json('dokumen_akad_files')->nullable()->after('dokumen_akad_nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fo_funnels', function (Blueprint $table) {
            $table->dropColumn('dokumen_akad_files');
        });
    }
};
