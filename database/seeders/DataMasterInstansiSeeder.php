<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataMasterInstansiSeeder extends Seeder
{
    /**
     * Instansi without a province/regency in the CSV (e.g. Kementerian) are attached to this regency.
     */
    private const NATIONAL_PROVINCE = 'Prov. DKI Jakarta';

    private const NATIONAL_REGENCY = 'Prov. DKI Jakarta';

    /**
     * Treats DataMasterInstansi.csv as the source of truth: [jenis, province name, regency name, instansi].
     * Empties pmo_opds and refills it from the CSV, after syncing regencies with their regions.
     * Contact and urusan links are restored by matching on regency + name.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/pmo/DataMasterInstansi.csv');

        if (! file_exists($csvPath)) {
            $this->command?->error("CSV tidak ditemukan: {$csvPath}");

            return;
        }

        $this->call(DataMasterRegionSeeder::class);

        [$toCreate, $unmatched] = $this->readCsv($csvPath);

        $restored = DB::transaction(function () use ($toCreate) {
            $contactKeys = $this->contactOpdKeys();
            $urusanKeys = $this->opdUrusanKeys();

            DB::table('pmo_opds')->delete();
            $this->resetIdSequence();

            $now = now();

            foreach (array_chunk(array_values($toCreate), 500) as $chunk) {
                DB::table('pmo_opds')->insert(array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk));
            }

            $this->syncIdSequence();

            return $this->restoreRelations($contactKeys, $urusanKeys);
        });

        $this->command?->info('Instansi diisi ulang: '.count($toCreate).", kontak dipulihkan: {$restored['contacts']}, relasi urusan dipulihkan: {$restored['urusans']}, baris wilayah tidak cocok: ".array_sum($unmatched));

        foreach ($unmatched as $line => $count) {
            $this->command?->warn("Wilayah tidak cocok: {$line} ({$count} baris)");
        }
    }

    /**
     * @return array{0: array<string, array{regency_id: int, name: string, jenis: string}>, 1: array<string, int>}
     */
    private function readCsv(string $csvPath): array
    {
        $regencyIds = $this->regencyIdsByProvinceAndName();

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle);

        $toCreate = [];
        $unmatched = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue;
            }

            [$jenis, $provinceName, $regencyName, $instansi] = array_map('trim', $row);

            if ($provinceName === '' && $regencyName === '') {
                [$provinceName, $regencyName] = [self::NATIONAL_PROVINCE, self::NATIONAL_REGENCY];
            }

            $regencyId = $this->findRegencyId($regencyIds, $provinceName, $regencyName);

            if (! $regencyId || $instansi === '') {
                $unmatched["{$provinceName} | {$regencyName}"] = ($unmatched["{$provinceName} | {$regencyName}"] ?? 0) + 1;

                continue;
            }

            $toCreate[$this->key($regencyId, $instansi)] ??= ['regency_id' => $regencyId, 'name' => $instansi, 'jenis' => $jenis];
        }

        fclose($handle);

        return [$toCreate, $unmatched];
    }

    private function key(int $regencyId, string $name): string
    {
        return $regencyId.'|'.mb_strtolower(trim($name));
    }

    /**
     * pmo_opds is referenced by foreign keys, so rows are deleted instead of TRUNCATE ... CASCADE (which would wipe contacts too).
     */
    private function resetIdSequence(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('pmo_opds', 'id'), 1, false)");
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
     * @return array<int, string> contact id => "regency_id|name" of its current OPD
     */
    private function contactOpdKeys(): array
    {
        return DB::table('pmo_contacts')
            ->join('pmo_opds', 'pmo_opds.id', '=', 'pmo_contacts.opd_id')
            ->get(['pmo_contacts.id as contact_id', 'pmo_opds.regency_id', 'pmo_opds.name'])
            ->mapWithKeys(fn ($row) => [$row->contact_id => $this->key($row->regency_id, $row->name)])
            ->all();
    }

    /**
     * @return array<int, array{key: string, urusan_id: int}>
     */
    private function opdUrusanKeys(): array
    {
        return DB::table('pmo_opd_urusan')
            ->join('pmo_opds', 'pmo_opds.id', '=', 'pmo_opd_urusan.opd_id')
            ->get(['pmo_opds.regency_id', 'pmo_opds.name', 'pmo_opd_urusan.urusan_id'])
            ->map(fn ($row) => ['key' => $this->key($row->regency_id, $row->name), 'urusan_id' => $row->urusan_id])
            ->all();
    }

    /**
     * @param  array<int, string>  $contactKeys
     * @param  array<int, array{key: string, urusan_id: int}>  $urusanKeys
     * @return array{contacts: int, urusans: int}
     */
    private function restoreRelations(array $contactKeys, array $urusanKeys): array
    {
        $newIds = [];

        foreach (DB::table('pmo_opds')->get(['id', 'regency_id', 'name']) as $opd) {
            $newIds[$this->key($opd->regency_id, $opd->name)] = $opd->id;
        }

        $contacts = 0;

        foreach ($contactKeys as $contactId => $key) {
            if (isset($newIds[$key])) {
                $contacts += DB::table('pmo_contacts')->where('id', $contactId)->update(['opd_id' => $newIds[$key]]);
            }
        }

        $pivot = [];

        foreach ($urusanKeys as $row) {
            if (isset($newIds[$row['key']])) {
                $pivot["{$newIds[$row['key']]}|{$row['urusan_id']}"] = ['opd_id' => $newIds[$row['key']], 'urusan_id' => $row['urusan_id']];
            }
        }

        foreach (array_chunk(array_values($pivot), 1000) as $chunk) {
            DB::table('pmo_opd_urusan')->insert($chunk);
        }

        return ['contacts' => $contacts, 'urusans' => count($pivot)];
    }

    /**
     * Falls back to a regency name that is unique across all provinces, for CSV rows filed under the wrong province.
     *
     * @param  array<string, int>  $regencyIds
     */
    private function findRegencyId(array $regencyIds, string $provinceName, string $regencyName): ?int
    {
        $provinceNames = [$provinceName, DataMasterRegionSeeder::PROVINCE_ALIASES[$provinceName] ?? $provinceName];
        $regencyNames = [$regencyName, ...(DataMasterRegionSeeder::NAME_ALIASES[$regencyName] ?? [])];

        foreach ($provinceNames as $province) {
            foreach ($regencyNames as $name) {
                if (isset($regencyIds["{$province}|{$name}"])) {
                    return $regencyIds["{$province}|{$name}"];
                }
            }
        }

        $matches = [];

        foreach ($regencyIds as $key => $id) {
            if (in_array(substr($key, strpos($key, '|') + 1), $regencyNames, true)) {
                $matches[$id] = $id;
            }
        }

        return count($matches) === 1 ? reset($matches) : null;
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
}
