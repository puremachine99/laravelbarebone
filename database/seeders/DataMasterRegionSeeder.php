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
     * Province names in the provinces table that are renamed to the spelling used in the CSV files.
     *
     * @var array<string, string>
     */
    public const PROVINCE_RENAMES = [
        'Prov. Daerah Istimewa Yogyakarta' => 'Prov. DI Yogyakarta',
        'Prov. Daerah Khusus Jakarta' => 'Prov. DKI Jakarta',
    ];

    /**
     * Province spellings in the instansi CSV that differ from the region CSV.
     *
     * @var array<string, string>
     */
    public const PROVINCE_ALIASES = [
        'Prov. Kepulauan Bangka Belitung' => 'Prov. Bangka Belitung',
    ];

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
        'Kota Padangsidempuan' => ['Kota Padangsidimpuan'],
        'Kota Pangkalpinang' => ['Kota Pangkal Pinang'],
        'Prov. DKI Jakarta' => ['Prov. Daerah Khusus Jakarta', 'Prov. Daerah Khusus Jakarta 1'],
        'Kab. Pangkajene dan Kepulauan (Pangkep)' => ['Kab. Pangkajene Dan Kepulauan', 'Kab. Pangkajene dan Kepulauan'],
        'Kab. Sidenreng Rappang (Sidrap)' => ['Kab. Sidenreng Rappang'],
        'Kab. Sitaro' => ['Kab. Kepulauan Siau Tagulandang Biaro'],
        'Kab. Ogan Komering Ulu (OKU)' => ['Kab. Ogan Komering Ulu'],
        'Kab. OKU Selatan' => ['Kab. Ogan Komering Ulu Selatan'],
        'Kab. OKU Timur' => ['Kab. Ogan Komering Ulu Timur'],
        'Kab. Ogan Komering Ilir (OKI)' => ['Kab. Ogan Komering Ilir'],
        'Kab. Penukal Abab Lematang Ilir (PALI)' => ['Kab. Penukal Abab Lematang Ilir'],
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

        $this->renameProvinces();

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

    private function renameProvinces(): void
    {
        foreach (self::PROVINCE_RENAMES as $from => $to) {
            if (! Province::query()->where('name', $to)->exists()) {
                Province::query()->where('name', $from)->update(['name' => $to]);
            }
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
