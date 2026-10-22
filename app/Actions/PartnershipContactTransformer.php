<?php

namespace App\Actions;

use App\Models\ContactPosition;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnershipContactTransformer
{
    private ?array $jabatanTranslationMap = null;

    /**
     * MAIN TRANSFORMER
     */
    public function transform(object $raw): array
    {
        $geo = $this->mapGeography(
            $raw->kontak_provinsi ?? null,
            $raw->kontak_kabupaten ?? null,
            $raw->kontak_ukw ?? null
        );

        $opdId = $this->mapOpd(
            $raw->kontak_instansi ?? null,
            $geo['regency_id']
        );

        return [
            'partnership_contact_id' => $raw->id,
            'name' => trim($raw->kontak_nama),
            'sumber' => $this->mapSource($raw->kontak_sumber ?? null),
            'category' => $this->mapCategory($raw->kontak_kategori ?? null),
            'regency_id' => $geo['regency_id'],
            'region_id' => $geo['region_id'],
            'opd_id' => $opdId,
            'pic_employee_id' => $this->mapPic($raw->kontak_pic_name ?? null),
            'contact_position_id' => $this->mapPosition($raw->kontak_jabatan ?? null),
            'phone' => $this->sanitizePhone($raw->kontak_hp ?? null),
            'email' => ! empty($raw->kontak_email) && filter_var($raw->kontak_email, FILTER_VALIDATE_EMAIL) ? trim($raw->kontak_email) : null,
            'created_at' => $raw->created_at ?? null,
            'updated_at' => $raw->updated_at ?? null,
        ];
    }

    /**
     * SANITIZE PHONE
     */
    public function sanitizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        if (Str::startsWith($cleaned, '08')) {
            return '+628'.substr($cleaned, 2);
        }

        if (Str::startsWith($cleaned, '8')) {
            return '+628'.substr($cleaned, 1);
        }

        if (Str::startsWith($cleaned, '62')) {
            return '+'.$cleaned;
        }

        return '+'.$cleaned;
    }

    /**
     * MAP CATEGORY
     */
    public function mapCategory(?string $category): string
    {
        if (empty($category)) {
            return 'Non Pemerintah';
        }

        $lower = Str::lower(trim($category));
        if (str_contains($lower, 'pemda') || str_contains($lower, 'pemerintah daerah') || str_contains($lower, 'dinas') || str_contains($lower, 'kabupaten') || str_contains($lower, 'provinsi')) {
            return 'Pemerintah Daerah';
        }
        if (str_contains($lower, 'kementerian') || str_contains($lower, 'lembaga') || str_contains($lower, 'kemen')) {
            return 'Kementerian / Lembaga';
        }

        return 'Non Pemerintah';
    }

    /**
     * MAP GEOGRAPHY
     */
    public function mapGeography(?string $provinceName, ?string $kabupatenCode, ?string $ukw): array
    {
        $regencyId = null;
        $regionId = null;

        if (! empty($kabupatenCode)) {
            $kabupatenCode = trim($kabupatenCode);

            $regency = Regency::where('code', $kabupatenCode)->first();

            if (! $regency && is_numeric($kabupatenCode) && ! str_contains($kabupatenCode, '.')) {
                $regency = Regency::where('id', (int) $kabupatenCode)->first();
            }

            if ($regency) {
                $regencyId = $regency->id;
                $regionId = $regency->region_id;
            }
        }

        if (! empty($ukw)) {
            $region = Region::where('id', (int) $ukw)
                ->orWhere('code', trim($ukw))
                ->first();

            if ($region) {
                $regionId = $region->id;
            }
        }

        if (is_null($regencyId) && ! empty($kabupatenCode) && ! is_numeric($kabupatenCode)) {
            $cleanKab = $this->cleanGeoName($kabupatenCode);

            $regency = Regency::whereRaw("LOWER(REPLACE(REPLACE(name, 'KABUPATEN ', ''), 'KOTA ', '')) = ?", [$cleanKab])
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($kabupatenCode)])
                ->first();

            if ($regency) {
                $regencyId = $regency->id;
                if (is_null($regionId)) {
                    $regionId = $regency->region_id;
                }
            }
        }

        if (is_null($regionId) && ! empty($provinceName)) {
            $cleanProv = $this->cleanGeoName($provinceName);
            $province = Province::whereRaw('LOWER(name) = ?', [Str::lower(trim($provinceName))])
                ->orWhereRaw("LOWER(REPLACE(name, 'PROVINSI ', '')) = ?", [$cleanProv])
                ->first();

            if ($province) {
                $regionId = Regency::where('province_id', $province->id)
                    ->whereNotNull('region_id')
                    ->value('region_id');
            }
        }

        return [
            'regency_id' => $regencyId,
            'region_id' => $regionId,
        ];
    }

    private function cleanGeoName(string $name): string
    {
        $name = Str::upper($name);
        $name = str_replace(['KABUPATEN', 'KAB.', 'KOTA', 'PROVINSI', 'PROV.'], '', $name);

        return trim(Str::lower($name));
    }

    /**
     * MAP SOURCE
     */
    public function mapSource(?string $source): string
    {
        if (empty($source)) {
            return 'SDS Umum';
        }

        $lower = Str::lower(trim($source));
        if (str_contains($lower, 'wilayah') || str_contains($lower, 'daerah')) {
            return 'SDS Wilayah';
        }
        if (str_contains($lower, 'referal') || str_contains($lower, 'rekomendasi')) {
            return 'Referal';
        }
        if (str_contains($lower, 'visit') || str_contains($lower, 'pitching') || str_contains($lower, 'kunjungan')) {
            return 'Visit/ Pitching';
        }
        if (str_contains($lower, 'sosial') || str_contains($lower, 'medsos') || str_contains($lower, 'instagram') || str_contains($lower, 'facebook')) {
            return 'Media Sosial Instansi';
        }
        if (str_contains($lower, 'service') || str_contains($lower, 'cs') || str_contains($lower, 'hubungi')) {
            return 'Customer Service';
        }

        return 'SDS Umum';
    }

    /**
     * MAP PIC (Berdasarkan Nama Lengkap, case-insensitive)
     */
    public function mapPic(?string $picName): ?int
    {
        if (empty($picName)) {
            return null;
        }

        $cleanName = strtolower(trim($picName));

        if (\strlen($cleanName) < 3) {
            return null;
        }

        $employee = Employee::whereHas('user', function ($query) use ($cleanName) {
            $query->whereRaw('LOWER(name) = ?', [$cleanName]);
        })->first();

        if ($employee) {
            return $employee->id;
        }

        $employeeFuzzy = Employee::whereHas('user', function ($query) use ($cleanName) {
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$cleanName}%"]);
        })->first();

        return $employeeFuzzy ? $employeeFuzzy->id : null;
    }

    /**
     * MAP POSITION (JABATAN)
     */
    private function loadJabatanTranslations(): array
    {
        if (! is_null($this->jabatanTranslationMap)) {
            return $this->jabatanTranslationMap;
        }

        $this->jabatanTranslationMap = [];
        $csvPath = database_path('seeders/jsonlawas/translate-jabatan.csv');
        if (file_exists($csvPath)) {
            $file = fopen($csvPath, 'r');
            fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                if (empty($row) || ! isset($row[0])) {
                    continue;
                }
                $unformated = strtolower(trim($row[0]));
                $formated = trim($row[1] ?? '');
                $this->jabatanTranslationMap[$unformated] = $formated;
            }
            fclose($file);
        }

        return $this->jabatanTranslationMap;
    }

    public function mapPosition(?string $positionName): ?int
    {
        if (empty($positionName)) {
            return null;
        }

        $positionName = trim($positionName);
        $translations = $this->loadJabatanTranslations();
        $lowerPos = strtolower($positionName);

        if (array_key_exists($lowerPos, $translations)) {
            $translatedName = $translations[$lowerPos];
            if ($translatedName === '') {
                return null;
            }
            $positionName = $translatedName;
        }

        $position = ContactPosition::whereRaw('LOWER(name) = ?', [Str::lower($positionName)])
            ->first();

        if ($position) {
            return $position->id;
        }

        $positionFuzzy = ContactPosition::where('name', 'like', '%'.$positionName.'%')
            ->first();

        return $positionFuzzy ? $positionFuzzy->id : null;
    }

    /**
     * MAP OPD (INSTANSI)
     */
    public function mapOpd(?string $externalInstansiId, ?int $regencyId): ?int
    {
        if (empty($externalInstansiId)) {
            return null;
        }

        try {
            $connection = 'mysql_partnership';
            if (config("database.connections.{$connection}")) {
                $externalInstansi = DB::connection($connection)
                    ->table('instansis')
                    ->where('id', $externalInstansiId)
                    ->first();

                if ($externalInstansi) {
                    $instansiName = trim($externalInstansi->instansi);

                    // Kementerian have no regency, so they are matched on name alone.
                    $kementerianId = Opd::kementerian()
                        ->whereRaw('LOWER(name) = ?', [Str::lower($instansiName)])
                        ->value('id');

                    if ($kementerianId || empty($regencyId)) {
                        return $kementerianId;
                    }

                    $opd = Opd::where('regency_id', $regencyId)
                        ->where('name', $instansiName)
                        ->first();

                    if ($opd) {
                        return $opd->id;
                    }

                    $opdFuzzy = Opd::where('regency_id', $regencyId)
                        ->where('name', 'like', '%'.$instansiName.'%')
                        ->first();

                    if ($opdFuzzy) {
                        return $opdFuzzy->id;
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return null;
    }
}
