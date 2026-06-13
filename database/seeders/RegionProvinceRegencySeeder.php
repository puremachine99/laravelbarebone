<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionProvinceRegencySeeder extends Seeder
{
    public function run(): void
    {
        // 1. REGIONS
        $regions = [
            ['code' => 'REG-1', 'name' => 'Region 1'],
            ['code' => 'REG-2', 'name' => 'Region 2'],
            ['code' => 'REG-3', 'name' => 'Region 3'],
            ['code' => 'REG-4', 'name' => 'Region 4'],
            ['code' => 'REG-5', 'name' => 'Region 5'],
            ['code' => 'REG-P', 'name' => 'Pemerintahan'],
        ];

        foreach ($regions as $region) {
            DB::table('regions')->insertOrIgnore([
                'name' => $region['name'],
                'code' => $region['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. PROVINCES
        $provincesJsonPath = database_path('seeders/jsonlawas/provinsis.json');
        if (file_exists($provincesJsonPath)) {
            $provinces = json_decode(file_get_contents($provincesJsonPath), true);
            foreach ($provinces as $prov) {
                DB::table('provinces')->insertOrIgnore([
                    'id' => $prov['id'],
                    'name' => $prov['provinsi'],
                    'code' => $prov['kode'],
                    'created_at' => $prov['created_at'] ?? now(),
                    'updated_at' => $prov['updated_at'] ?? now(),
                ]);
            }
        }

        // Map province code to ID for fast lookup
        $provinceIdMap = DB::table('provinces')->pluck('id', 'code')->toArray();

        // 3. REGENCIES
        $kabupatensJsonPath = database_path('seeders/jsonlawas/kabupatens.json');
        if (file_exists($kabupatensJsonPath)) {
            $rawContent = file_get_contents($kabupatensJsonPath);
            $cleanContent = str_replace("\'", "'", $rawContent);
            $kabupatens = json_decode($cleanContent, true);
            foreach ($kabupatens as $row) {
                $type = 'Kabupaten';
                if (str_starts_with($row['kabupaten'], 'Prov.')) {
                    $type = 'Provinsi';
                } elseif ($row['kode_provinsi'] === '39') {
                    $type = 'Lembaga';
                } elseif ($row['kode_provinsi'] === '40') {
                    $type = 'Non-Pemerintahan';
                } elseif (str_starts_with($row['kabupaten'], 'Kota ')) {
                    $type = 'Kota';
                }

                $provinceId = $provinceIdMap[$row['kode_provinsi']] ?? null;

                if ($provinceId) {
                    DB::table('regencies')->insertOrIgnore([
                        'id' => $row['id'],
                        'province_id' => $provinceId,
                        'region_id' => $row['ukw'] ?: null, // ukw = region_id
                        'name' => $row['kabupaten'],
                        'code' => $row['kode'],
                        'type' => $type,
                        'created_at' => $row['created_at'] ?? now(),
                        'updated_at' => $row['updated_at'] ?? now(),
                    ]);
                }
            }
        }
    }
}
