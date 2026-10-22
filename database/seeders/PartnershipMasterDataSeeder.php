<?php

namespace Database\Seeders;

use App\Enums\OpdJenis;
use App\Models\Regency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master data for the Partnership module, read from database/seeders/pmo/Master*.csv:
 *
 * - Region 1─n Regency, Province 1─n Regency (each regency has exactly one province and one region).
 * - OPD, Unit Kesehatan and Pendidikan belong to a regency (pmo_opds.regency_id).
 * - Kementerian belongs to a region (pmo_opds.region_id), never to a province or regency.
 *
 * Safe to re-run: rows are matched on normalized names and updated in place, so contact and urusan links keep
 * pointing at the same ids. Instansi missing from the CSVs are reported, not deleted.
 */
class PartnershipMasterDataSeeder extends Seeder
{
    /**
     * Kabupaten/kota name the OPD CSV uses for province-level OPDs; they go to the province's "Provinsi" regency.
     */
    public const PROVINCE_LEVEL_REGENCY = 'Tingkat Pemerintah Provinsi';

    /**
     * Pseudo-province of the old data that held each Kementerian as a "regency". Replaced by OpdJenis::Kementerian.
     */
    public const KEMENTERIAN_PSEUDO_PROVINCE = 'Kementerian/Lembaga';

    /**
     * Province spellings found in the CSVs and the old data, mapped to the name in MasterProvinsi.csv.
     *
     * @var array<string, string>
     */
    public const PROVINCE_ALIASES = [
        'Prov. DIY' => 'Prov. DI Yogyakarta',
        'Prov. Daerah Istimewa Yogyakarta' => 'Prov. DI Yogyakarta',
        'Prov. Daerah Khusus Jakarta' => 'Prov. DKI Jakarta',
        'Prov. Daerah Khusus Jakarta (DKJ)' => 'Prov. DKI Jakarta',
        'Prov. Bangka Belitung' => 'Prov. Kepulauan Bangka Belitung',
    ];

    /**
     * Kabupaten/kota spellings that differ beyond case, spacing, punctuation or a "(singkatan)" suffix, mapped to
     * the name in MasterRegency.csv.
     *
     * @var array<string, string>
     */
    public const REGENCY_ALIASES = [
        'Prov. DIY' => 'Prov. DI Yogyakarta',
        'Prov. Daerah Istimewa Yogyakarta' => 'Prov. DI Yogyakarta',
        'Prov. Daerah Khusus Jakarta' => 'Prov. DKI Jakarta',
        'Prov. Bangka Belitung' => 'Prov. Kepulauan Bangka Belitung',
        'Kota Padangsidempuan' => 'Kota Padangsidimpuan',
        'Kab. OKU Selatan' => 'Kab. Ogan Komering Ulu Selatan',
        'Kab. OKU Timur' => 'Kab. Ogan Komering Ulu Timur',
        'Kab. Sitaro' => 'Kab. Kepulauan Siau Tagulandang Biaro',
    ];

    /**
     * Old Kementerian/Lembaga names (from the pseudo-province) mapped to the nomenclature in MasterKementrian.csv,
     * for differences beyond case, spacing, punctuation or a "(singkatan)" suffix.
     *
     * @var array<string, string>
     */
    public const KEMENTERIAN_ALIASES = [
        'DPR RI' => 'Dewan Perwakilan Rakyat (DPR)',
        'Kejaksaan Agung' => 'Jaksa Agung (Kejaksaan Agung RI)',
        'Komisi Ombudsman' => 'Ombudsman Republik Indonesia',
        'LPP TVRI' => 'Lembaga Penyiaran Publik Televisi Republik Indonesia (LPP TVRI)',
        'LPP RRI' => 'Lembaga Penyiaran Publik Radio Republik Indonesia (LPP RRI)',
        'Perpustakaan Nasional Republik Indonesia' => 'Perpustakaan Nasional RI (Perpusnas)',
        'Kementerian Pariwisata' => 'Kementerian Pariwisata Republik Indonesia (Kemenpar)',
        'Kementerian Imigrasi Dan Pemasyarakatan RI' => 'Kementerian Imigrasi dan Pemasyarakatan',
        'Kementerian Usaha Mikro, Kecil, Dan Menengah' => 'Kementerian Usaha Kecil, Mikro, dan Menengah (UMKM)',
        'Kementerian Perencanaan Pembangunan Nasional/Bappenas' => 'Kementerian Perencanaan Pembangunan Nasional/Badan Perencanaan Pembangunan Nasional (Bappenas)',
        'Kementerian Investasi Dan Hilirisasi /BKPM' => 'Kementerian Investasi dan Hilirisasi/Badan Koordinasi Penanaman Modal',
        'Badan Koordinasi Penanaman Modal' => 'Kementerian Investasi dan Hilirisasi/Badan Koordinasi Penanaman Modal',
        'Badan Nasional Pengelola Perbatasan' => 'Badan Pengelola Perbatasan Negara',
        'Badan Pengawas Pemilu' => 'Badan Pengawas Pemilihan Umum (Bawaslu)',
        'Badan Standarisasi Nasional' => 'Badan Standardisasi Nasional (BSN)',
        'Komisi Pengawasan Persaingan Usaha' => 'Komisi Pengawas Persaingan Usaha (KPPU)',
        'Lembaga Ketahanan Nasional' => 'Lembaga Ketahanan Nasional Republik Indonesia (Lemhannas RI)',
    ];

    /**
     * @var array<string, int> region number => region id
     */
    private array $regionIds = [];

    /**
     * @var array<string, int> province key => province id
     */
    private array $provinceIds = [];

    /**
     * @var array<string, int> "province id|regency key" => regency id
     */
    private array $regencyIds = [];

    /**
     * @var array<string, list<int>> regency key => regency ids in any province, for rows filed under the wrong province
     */
    private array $regencyIdsByName = [];

    /**
     * @var array<string, array<string, string>> alias map name => (geo key => canonical name)
     */
    private array $aliasesByKey = [];

    /**
     * @var array<string, int>
     */
    private array $report = [];

    /**
     * @var array<string, int> "file | provinsi | kab/kota" => rows skipped
     */
    private array $unmatched = [];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (['regions', 'provinces', 'regencies', 'pmo_opds'] as $table) {
                $this->syncIdSequence($table);
            }

            $this->seedRegions();
            $this->seedProvinces();
            $this->seedRegencies();
            $this->seedKementerian();
            $this->seedRegencyInstansi('MasterOPD.csv', OpdJenis::Opd);
            $this->seedRegencyInstansi('MasterUnitKesehatan.csv', OpdJenis::UnitKesehatan);
            $this->seedRegencyInstansi('MasterPendidikan.csv', OpdJenis::Pendidikan);
            $this->removeKementerianPseudoProvince();
        });

        foreach ($this->report as $label => $count) {
            $this->command?->info("{$label}: {$count}");
        }

        foreach ($this->unmatched as $line => $count) {
            $this->command?->warn("Wilayah tidak cocok: {$line} ({$count} baris)");
        }
    }

    /**
     * Lowercase, without "(singkatan)" suffixes, punctuation or spaces: "Kab. Muko muko" and "Kab. Mukomuko" match.
     */
    public static function geoKey(string $name): string
    {
        $name = preg_replace('/\([^)]*\)/u', '', mb_strtolower(trim($name)));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
    }

    public static function instansiKey(string $name): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($name)));
    }

    private function provinceKey(string $name): string
    {
        return self::geoKey($this->alias(self::PROVINCE_ALIASES, $name));
    }

    private function regencyKey(string $name): string
    {
        return self::geoKey($this->alias(self::REGENCY_ALIASES, $name));
    }

    /**
     * @param  array<string, string>  $aliases
     */
    private function alias(array $aliases, string $name): string
    {
        $cacheKey = match ($aliases) {
            self::PROVINCE_ALIASES => 'province',
            self::KEMENTERIAN_ALIASES => 'kementerian',
            default => 'regency',
        };

        $this->aliasesByKey[$cacheKey] ??= collect($aliases)
            ->mapWithKeys(fn (string $to, string $from) => [self::geoKey($from) => $to])
            ->all();

        return $this->aliasesByKey[$cacheKey][self::geoKey($name)] ?? $name;
    }

    /**
     * Region 1-5 from the REGION column of MasterRegency.csv and MasterKementrian.csv, stored as code "REG-{n}".
     */
    private function seedRegions(): void
    {
        $numbers = collect($this->readCsv('MasterRegency.csv'))->pluck(2)
            ->merge(collect($this->readCsv('MasterKementrian.csv'))->pluck(2))
            ->map(fn ($number) => trim((string) $number))
            ->filter(fn (string $number) => $number !== '')
            ->unique()
            ->sort();

        foreach ($numbers as $number) {
            $code = "REG-{$number}";
            $id = DB::table('regions')->where('code', $code)->value('id');

            if (! $id) {
                $id = DB::table('regions')->insertGetId(['name' => "Region {$number}", 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
                $this->count('Region dibuat');
            }

            $this->regionIds[$number] = $id;
        }
    }

    private function seedProvinces(): void
    {
        $existing = DB::table('provinces')->get(['id', 'name'])
            ->mapWithKeys(fn ($province) => [$this->provinceKey($province->name) => $province])
            ->all();

        foreach ($this->readCsv('MasterProvinsi.csv') as [$name]) {
            $name = trim($name);
            $key = $this->provinceKey($name);

            if (isset($existing[$key])) {
                if ($existing[$key]->name !== $name) {
                    DB::table('provinces')->where('id', $existing[$key]->id)->update(['name' => $name, 'updated_at' => now()]);
                    $this->count('Provinsi diganti nama');
                }

                $this->provinceIds[$key] = $existing[$key]->id;

                continue;
            }

            $this->provinceIds[$key] = DB::table('provinces')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $this->count('Provinsi dibuat');
        }
    }

    private function seedRegencies(): void
    {
        $existing = [];

        foreach (DB::table('regencies')->get(['id', 'province_id', 'region_id', 'name', 'type']) as $regency) {
            $existing["{$regency->province_id}|{$this->regencyKey($regency->name)}"] = $regency;
        }

        foreach ($this->readCsv('MasterRegency.csv') as $row) {
            [$provinceName, $name, $regionNumber] = array_map('trim', $row + [null, null, null]);
            $provinceId = $this->provinceIds[$this->provinceKey($provinceName)] ?? null;
            $regionId = $this->regionIds[$regionNumber] ?? null;

            if (! $provinceId || $name === '') {
                $this->miss('MasterRegency.csv', $provinceName, $name);

                continue;
            }

            $key = "{$provinceId}|{$this->regencyKey($name)}";
            $attributes = ['name' => $name, 'region_id' => $regionId, 'type' => $this->regencyType($name)];

            if (isset($existing[$key])) {
                $current = $existing[$key];

                if ($current->name !== $name || $current->region_id !== $regionId || $current->type !== $attributes['type']) {
                    DB::table('regencies')->where('id', $current->id)->update($attributes + ['updated_at' => now()]);
                    $this->count('Kabupaten/kota diperbarui');
                }

                $id = $current->id;
            } else {
                $id = DB::table('regencies')->insertGetId($attributes + ['province_id' => $provinceId, 'created_at' => now(), 'updated_at' => now()]);
                $this->count('Kabupaten/kota dibuat');
            }

            $this->regencyIds[$key] = $id;
            $this->regencyIdsByName[$this->regencyKey($name)][$id] = $id;
        }
    }

    private function regencyType(string $name): string
    {
        return match (true) {
            str_starts_with($name, 'Prov.') => Regency::TYPE_PROVINCE,
            str_starts_with($name, 'Kota ') => 'Kota',
            default => 'Kabupaten',
        };
    }

    private function seedKementerian(): void
    {
        $existing = DB::table('pmo_opds')->where('jenis', OpdJenis::Kementerian->value)->get(['id', 'name', 'region_id'])
            ->mapWithKeys(fn ($opd) => [self::instansiKey($opd->name) => $opd])
            ->all();
        $defaultRegionId = DB::table('regions')->where('code', OpdJenis::KEMENTERIAN_DEFAULT_REGION_CODE)->value('id');

        foreach ($this->readCsv('MasterKementrian.csv') as $row) {
            [, $name, $regionNumber] = array_map('trim', $row + [null, null, null]);

            if ($name === '') {
                continue;
            }

            $regionId = $this->regionIds[$regionNumber] ?? $defaultRegionId;
            $key = self::instansiKey($name);

            if (isset($existing[$key])) {
                if ($existing[$key]->region_id !== $regionId) {
                    DB::table('pmo_opds')->where('id', $existing[$key]->id)->update(['region_id' => $regionId, 'updated_at' => now()]);
                }

                continue;
            }

            $existing[$key] = (object) ['id' => DB::table('pmo_opds')->insertGetId([
                'name' => $name,
                'jenis' => OpdJenis::Kementerian->value,
                'region_id' => $regionId,
                'regency_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]), 'region_id' => $regionId];
            $this->count('Kementerian dibuat');
        }
    }

    /**
     * Instansi CSV rows of [provinsi, kabupaten/kota, nama]; a header row starting with "Provinsi" is skipped.
     * Missing files are skipped, so MasterPendidikan.csv can be added later.
     */
    private function seedRegencyInstansi(string $file, OpdJenis $jenis): void
    {
        if (! file_exists($this->path($file))) {
            return;
        }

        $existing = [];

        foreach (DB::table('pmo_opds')->whereNotNull('regency_id')->get(['id', 'regency_id', 'name', 'jenis']) as $opd) {
            $existing["{$opd->regency_id}|".self::instansiKey($opd->name)] = $opd;
        }

        $toInsert = [];

        foreach ($this->readCsv($file) as $row) {
            [$provinceName, $regencyName, $name] = array_map('trim', $row + [null, null, null]);

            if ($name === '') {
                continue;
            }

            $regencyId = $this->findRegencyId($provinceName, $regencyName);

            if (! $regencyId) {
                $this->miss($file, $provinceName, $regencyName);

                continue;
            }

            $key = "{$regencyId}|".self::instansiKey($name);

            if (isset($existing[$key])) {
                if ($existing[$key]->jenis !== $jenis->value) {
                    DB::table('pmo_opds')->where('id', $existing[$key]->id)->update(['jenis' => $jenis->value, 'updated_at' => now()]);
                    $existing[$key]->jenis = $jenis->value;
                    $this->count("{$jenis->value} diubah jenisnya");
                }

                continue;
            }

            $existing[$key] = (object) ['id' => null, 'jenis' => $jenis->value];
            $toInsert[] = ['regency_id' => $regencyId, 'name' => $name, 'jenis' => $jenis->value, 'created_at' => now(), 'updated_at' => now()];
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            DB::table('pmo_opds')->insert($chunk);
        }

        $this->report["{$jenis->value} dibuat"] = count($toInsert);
    }

    private function findRegencyId(string $provinceName, string $regencyName): ?int
    {
        $provinceId = $this->provinceIds[$this->provinceKey($provinceName)] ?? null;

        if ($regencyName === '' || self::geoKey($regencyName) === self::geoKey(self::PROVINCE_LEVEL_REGENCY)) {
            $regencyName = $this->alias(self::PROVINCE_ALIASES, $provinceName);
        }

        $regencyKey = $this->regencyKey($regencyName);

        if ($provinceId && isset($this->regencyIds["{$provinceId}|{$regencyKey}"])) {
            return $this->regencyIds["{$provinceId}|{$regencyKey}"];
        }

        $matches = $this->regencyIdsByName[$regencyKey] ?? [];

        return count($matches) === 1 ? reset($matches) : null;
    }

    /**
     * Moves contacts off the old "Kementerian/Lembaga" pseudo-province onto the matching Kementerian, creating the
     * Kementerian (on the pseudo regency's region) when MasterKementrian.csv has none, then removes the pseudo regencies.
     */
    private function removeKementerianPseudoProvince(): void
    {
        $provinceId = DB::table('provinces')->where('name', self::KEMENTERIAN_PSEUDO_PROVINCE)->value('id');

        if (! $provinceId) {
            return;
        }

        $kementerianIds = DB::table('pmo_opds')->where('jenis', OpdJenis::Kementerian->value)->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [self::geoKey($name) => $id])
            ->all();
        $defaultRegionId = DB::table('regions')->where('code', OpdJenis::KEMENTERIAN_DEFAULT_REGION_CODE)->value('id');

        foreach (DB::table('regencies')->where('province_id', $provinceId)->get(['id', 'name', 'region_id']) as $regency) {
            $name = trim(preg_replace('/\s+/u', ' ', $regency->name));
            $key = self::geoKey($this->alias(self::KEMENTERIAN_ALIASES, $name));

            if (! isset($kementerianIds[$key])) {
                $kementerianIds[$key] = DB::table('pmo_opds')->insertGetId([
                    'name' => $name,
                    'jenis' => OpdJenis::Kementerian->value,
                    'region_id' => $regency->region_id ?? $defaultRegionId,
                    'regency_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->count('Kementerian dibuat dari data lama');
            }

            $contacts = DB::table('pmo_contacts')->where('regency_id', $regency->id);

            $this->report['Kontak dipindah ke Kementerian'] = ($this->report['Kontak dipindah ke Kementerian'] ?? 0)
                + (clone $contacts)->whereNull('opd_id')->update(['opd_id' => $kementerianIds[$key], 'regency_id' => null, 'updated_at' => now()]);

            $contacts->update(['regency_id' => null, 'updated_at' => now()]);

            if (DB::table('pmo_opds')->where('regency_id', $regency->id)->exists()) {
                $this->miss('Regency Kementerian lama masih dipakai OPD', self::KEMENTERIAN_PSEUDO_PROVINCE, $regency->name);

                continue;
            }

            DB::table('regencies')->where('id', $regency->id)->delete();
            $this->count('Regency Kementerian lama dihapus');
        }

        if (! DB::table('regencies')->where('province_id', $provinceId)->exists()) {
            DB::table('provinces')->where('id', $provinceId)->delete();
            $this->count('Pseudo-provinsi Kementerian/Lembaga dihapus');
        }
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $file): array
    {
        if (! file_exists($this->path($file))) {
            return [];
        }

        $handle = fopen($this->path($file), 'r');
        $header = fgetcsv($handle);
        $rows = [];

        if ($header && ! $this->isHeader($header)) {
            $rows[] = array_map(fn ($value) => (string) $value, $header);
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row !== [null]) {
                $rows[] = array_map(fn ($value) => (string) $value, $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isHeader(array $row): bool
    {
        $first = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0])));

        return in_array($first, ['provinsi', 'kategori'], true);
    }

    private function path(string $file): string
    {
        return database_path("seeders/pmo/{$file}");
    }

    private function count(string $label): void
    {
        $this->report[$label] = ($this->report[$label] ?? 0) + 1;
    }

    private function miss(string $file, ?string $province, ?string $regency): void
    {
        $line = "{$file} | {$province} | {$regency}";
        $this->unmatched[$line] = ($this->unmatched[$line] ?? 0) + 1;
    }

    /**
     * Older seeders insert explicit ids, which leaves the PostgreSQL sequence behind the real max(id).
     */
    private function syncIdSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
    }
}
