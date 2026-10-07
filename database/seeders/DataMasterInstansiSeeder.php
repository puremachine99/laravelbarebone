<?php

namespace Database\Seeders;

use App\Models\Opd;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataMasterInstansiSeeder extends Seeder
{
    /**
     * Treats DataMasterInstansi.csv as the source of truth: [jenis, province name, regency name, instansi].
     * Sets "jenis" on existing OPDs (matched by regency + name) and creates the ones that are missing.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/pmo/DataMasterInstansi.csv');

        if (! file_exists($csvPath)) {
            $this->command?->error("CSV tidak ditemukan: {$csvPath}");

            return;
        }

        $regencyIds = $this->regencyIdsByProvinceAndName();
        $opdIds = $this->opdIdsByRegencyAndName();

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle);

        $idsByJenis = [];
        $toCreate = [];
        $unmatched = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue;
            }

            [$jenis, $provinceName, $regencyName, $instansi] = array_map('trim', $row);

            $regencyId = $this->findRegencyId($regencyIds, $provinceName, $regencyName);

            if (! $regencyId || $instansi === '') {
                $unmatched["{$provinceName} | {$regencyName}"] = ($unmatched["{$provinceName} | {$regencyName}"] ?? 0) + 1;

                continue;
            }

            $key = $regencyId.'|'.mb_strtolower($instansi);

            if (isset($opdIds[$key])) {
                $idsByJenis[$jenis] = array_merge($idsByJenis[$jenis] ?? [], $opdIds[$key]);

                continue;
            }

            $toCreate[$key] = ['regency_id' => $regencyId, 'name' => $instansi, 'jenis' => $jenis];
        }

        fclose($handle);

        $updated = 0;

        foreach ($idsByJenis as $jenis => $ids) {
            foreach (array_chunk(array_unique($ids), 1000) as $chunk) {
                $updated += Opd::query()->whereIn('id', $chunk)->where(fn ($q) => $q->whereNull('jenis')->orWhere('jenis', '!=', $jenis))->update(['jenis' => $jenis]);
            }
        }

        $this->syncIdSequence();

        $now = now();
        $created = 0;

        foreach (array_chunk(array_values($toCreate), 500) as $chunk) {
            Opd::query()->insert(array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk));
            $created += count($chunk);
        }

        $this->command?->info("Jenis diperbarui: {$updated}, instansi baru: {$created}, wilayah tidak cocok: ".array_sum($unmatched));

        foreach ($unmatched as $line => $count) {
            $this->command?->warn("Wilayah tidak cocok: {$line} ({$count} baris)");
        }
    }

    /**
     * OpdSeeder inserts explicit ids, which leaves the PostgreSQL sequence behind the real max(id).
     */
    private function syncIdSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('pmo_opds', 'id'), COALESCE((SELECT MAX(id) FROM pmo_opds), 0) + 1, false)");
    }

    /**
     * @param  array<string, int>  $regencyIds
     */
    private function findRegencyId(array $regencyIds, string $provinceName, string $regencyName): ?int
    {
        foreach ([$regencyName, ...(DataMasterRegionSeeder::NAME_ALIASES[$regencyName] ?? [])] as $name) {
            if (isset($regencyIds["{$provinceName}|{$name}"])) {
                return $regencyIds["{$provinceName}|{$name}"];
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function regencyIdsByProvinceAndName(): array
    {
        return DB::table('regencies')
            ->join('provinces', 'provinces.id', '=', 'regencies.province_id')
            ->get(['regencies.id', 'regencies.name', 'provinces.name as province_name'])
            ->mapWithKeys(fn ($row) => ["{$row->province_name}|{$row->name}" => $row->id])
            ->all();
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function opdIdsByRegencyAndName(): array
    {
        $map = [];

        foreach (DB::table('pmo_opds')->get(['id', 'regency_id', 'name']) as $opd) {
            $map[$opd->regency_id.'|'.mb_strtolower(trim($opd->name))][] = $opd->id;
        }

        return $map;
    }
}
