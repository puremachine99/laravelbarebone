<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Database\Seeder;

class DataMasterRegionSeeder extends Seeder
{
    /**
     * @var array<string, int|null>
     */
    private array $regionIdsByNumber = [];

    /**
     * @var array<string, int|null>
     */
    private array $provinceIdsByName = [];

    /**
     * Alternative spellings found in the reference data, tried when the CSV name has no match.
     *
     * @var array<string, array<int, string>>
     */
    public const NAME_ALIASES = [
        'Prov. DI Yogyakarta' => ['Prov. DIY', 'Prov. Daerah Istimewa Yogyakarta'],
        'Prov. DKI Jakarta' => ['Prov. Daerah Khusus Jakarta'],
        'Kab. Gunungkidul' => ['Kab. Gunung Kidul'],
        'Kota Tanjungpinang' => ['Kota Tanjung Pinang'],
        'Kota Parepare' => ['Kota Pare-Pare', 'Kota Pare Pare'],
        'Kab. Batu Bara' => ['Kab. Batubara'],
        'Kota Padangsidimpuan' => ['Kota Padangsidempuan'],
    ];

    /**
     * Sets region_id on regencies (provinces are stored as regencies of type "Provinsi")
     * based on the CSV: [province name, regency name, region number].
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/pmo/DataMasterRegion.csv');

        if (! file_exists($csvPath)) {
            $this->command?->error("CSV tidak ditemukan: {$csvPath}");

            return;
        }

        $handle = fopen($csvPath, 'r');
        $updated = 0;
        $unchanged = 0;
        $unmatched = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }

            [$provinceName, $regencyName, $regionNumber] = array_map('trim', $row);

            $regionId = $this->resolveRegionId($regionNumber);
            $provinceId = $this->resolveProvinceId($provinceName);
            $regency = $provinceId
                ? Regency::query()
                    ->where('province_id', $provinceId)
                    ->whereIn('name', [$regencyName, ...(self::NAME_ALIASES[$regencyName] ?? [])])
                    ->first()
                : null;

            if (! $regionId || ! $regency) {
                $unmatched[] = "{$provinceName} | {$regencyName} | {$regionNumber}";

                continue;
            }

            if ($regency->region_id === $regionId) {
                $unchanged++;

                continue;
            }

            $regency->update(['region_id' => $regionId]);
            $updated++;
        }

        fclose($handle);

        $this->command?->info("Region diperbarui: {$updated}, sudah sesuai: {$unchanged}, tidak cocok: ".count($unmatched));

        foreach ($unmatched as $line) {
            $this->command?->warn("Tidak cocok: {$line}");
        }
    }

    private function resolveRegionId(string $regionNumber): ?int
    {
        if (! array_key_exists($regionNumber, $this->regionIdsByNumber)) {
            $this->regionIdsByNumber[$regionNumber] = Region::query()
                ->where('code', "REG-{$regionNumber}")
                ->value('id');
        }

        return $this->regionIdsByNumber[$regionNumber];
    }

    private function resolveProvinceId(string $provinceName): ?int
    {
        if (! array_key_exists($provinceName, $this->provinceIdsByName)) {
            $this->provinceIdsByName[$provinceName] = Province::query()
                ->where('name', $provinceName)
                ->value('id');
        }

        return $this->provinceIdsByName[$provinceName];
    }
}
