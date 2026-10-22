<?php

use App\Enums\OpdJenis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kementerian are national: they move off their placeholder regency (DKI Jakarta) onto a region. Every other
     * jenis keeps its regency and gets its region through it, so region_id is only filled for Kementerian.
     */
    public function up(): void
    {
        Schema::table('pmo_opds', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('regency_id')->index()->constrained('regions')->nullOnDelete();
            $table->foreignId('regency_id')->nullable()->change();
        });

        $kementerian = OpdJenis::Kementerian->value;
        $defaultRegionId = DB::table('regions')->where('code', OpdJenis::KEMENTERIAN_DEFAULT_REGION_CODE)->value('id');

        DB::table('pmo_opds')
            ->whereRaw('LOWER(jenis) IN (?, ?)', ['kementrian', 'kementerian'])
            ->update([
                'jenis' => $kementerian,
                'region_id' => DB::raw('COALESCE((SELECT regencies.region_id FROM regencies WHERE regencies.id = pmo_opds.regency_id), '.($defaultRegionId ? (int) $defaultRegionId : 'NULL').')'),
                'regency_id' => null,
            ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE pmo_opds ADD CONSTRAINT pmo_opds_location_by_jenis CHECK (
                (jenis = '{$kementerian}' AND regency_id IS NULL AND region_id IS NOT NULL)
                OR (jenis IS DISTINCT FROM '{$kementerian}' AND regency_id IS NOT NULL AND region_id IS NULL)
            )");
        }
    }

    /**
     * Kementerian lose their region and are left without a regency; re-run the master seeder to place them again.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pmo_opds DROP CONSTRAINT IF EXISTS pmo_opds_location_by_jenis');
        }

        Schema::table('pmo_opds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
