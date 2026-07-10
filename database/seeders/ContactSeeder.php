<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactSeeder extends Seeder
{
    private array $regions = [];

    private array $regencies = [];

    private array $employees = [];

    private array $positions = [];

    private array $opds = [];

    private array $jabatanMap = [];

    public function run(): void
    {
        $this->command->info('Pre-loading lookup tables...');
        $this->preloadLookups();

        $csvPath = database_path('seeders/jsonlawas/contact.csv');
        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found: {$csvPath}");

            return;
        }

        $this->command->info('Processing contact.csv (~22k rows)...');
        $file = fopen($csvPath, 'r');
        fgetcsv($file); // skip header

        $batch = [];
        $inserted = 0;
        $skipped = 0;
        $now = now()->toDateTimeString();

        while (($row = fgetcsv($file)) !== false) {
            $record = $this->transform($row, $now);
            if ($record === null) {
                $skipped++;

                continue;
            }

            $batch[] = $record;

            if (\count($batch) >= 200) {
                DB::table('pmo_contacts')->upsert(
                    $batch,
                    ['partnership_contact_id'],
                    ['name', 'sumber', 'category', 'region_id', 'pic_employee_id',
                        'regency_id', 'contact_position_id', 'opd_id', 'phone', 'email',
                        'created_at', 'updated_at']
                );
                $inserted += \count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('pmo_contacts')->upsert(
                $batch,
                ['partnership_contact_id'],
                ['name', 'sumber', 'category', 'region_id', 'pic_employee_id',
                    'regency_id', 'contact_position_id', 'opd_id', 'phone', 'email',
                    'created_at', 'updated_at']
            );
            $inserted += \count($batch);
        }

        fclose($file);
        $this->command->info("Done — Upserted: {$inserted} | Skipped: {$skipped}");
    }

    // ─────────────────────────────────────────────────────
    // PRE-LOAD
    // ─────────────────────────────────────────────────────

    private function preloadLookups(): void
    {
        // regions: id → id (existence check)
        $this->regions = DB::table('regions')->pluck('id', 'id')->toArray();

        // regencies: normalized_name → id
        DB::table('regencies')->select('id', 'name')->orderBy('id')->each(function ($r) {
            $key = $this->normalizeGeoName($r->name);
            $this->regencies[$key] = $r->id;
        });

        // employees: lowercase user name → employee.id
        DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->select('employees.id as emp_id', 'users.name as user_name')
            ->orderBy('employees.id')
            ->each(function ($e) {
                $this->employees[strtolower(trim($e->user_name))] = $e->emp_id;
            });

        // contact positions: lowercase name → id
        DB::table('pmo_contact_positions')->select('id', 'name')->orderBy('id')->each(function ($p) {
            $this->positions[strtolower(trim($p->name))] = $p->id;
        });

        // opds: "regency_id|lowercase_name" → id
        DB::table('pmo_opds')->select('id', 'regency_id', 'name')->orderBy('id')->each(function ($o) {
            $key = $o->regency_id.'|'.strtolower(trim($o->name));
            $this->opds[$key] = $o->id;
        });

        // jabatan translation csv
        $path = database_path('seeders/jsonlawas/translate-jabatan.csv');
        if (file_exists($path)) {
            $f = fopen($path, 'r');
            fgetcsv($f); // header
            while (($r = fgetcsv($f)) !== false) {
                if (! empty($r[0])) {
                    $this->jabatanMap[strtolower(trim($r[0]))] = trim($r[1] ?? '');
                }
            }
            fclose($f);
        }
    }

    // ─────────────────────────────────────────────────────
    // TRANSFORM
    // ─────────────────────────────────────────────────────

    /**
     * CSV columns (0-based):
     *  0  ID             → partnership_contact_id
     *  1  Nama           → name
     *  2  Sumber         → sumber
     *  3  UKW            → region_id
     *  4  PIC            → pic_employee_id
     *  5  Kategori       → category
     *  6  Provinsi       → (geo fallback)
     *  7  Kabupaten      → regency_id
     *  8  Jabatan        → (position fallback)
     *  9  Bidang         → contact_position_id (primary)
     * 10  Instansi       → opd_id
     * 11  Unit           → (ignored)
     * 12  Email          → email
     * 13  HP             → phone
     * 14-17 misc         → (ignored)
     * 18  Dibuat         → created_at
     */
    private function transform(array $r, string $now): ?array
    {
        $id = isset($r[0]) ? (int) trim($r[0]) : 0;
        $name = isset($r[1]) ? trim($r[1]) : '';

        if ($id === 0 || $name === '') {
            return null;
        }

        $regencyId = $this->resolveRegency(trim($r[7] ?? ''));
        $opdId = $this->resolveOpd(trim($r[10] ?? ''), $regencyId);
        $posId = $this->resolvePosition(trim($r[9] ?? ''), trim($r[8] ?? ''));
        $createdAt = $this->parseDate(trim($r[18] ?? ''), $now);

        return [
            'partnership_contact_id' => $id,
            'name' => $name,
            'sumber' => $this->mapSumber(trim($r[2] ?? '')),
            'category' => $this->mapKategori(trim($r[5] ?? '')),
            'region_id' => $this->resolveRegion((int) trim($r[3] ?? 0)),
            'pic_employee_id' => $this->resolveEmployee(trim($r[4] ?? '')),
            'regency_id' => $regencyId,
            'contact_position_id' => $posId,
            'opd_id' => $opdId,
            'phone' => $this->sanitizePhone(trim($r[13] ?? '')),
            'email' => $this->sanitizeEmail(trim($r[12] ?? '')),
            'created_at' => $createdAt,
            'updated_at' => $now,
        ];
    }

    // ─────────────────────────────────────────────────────
    // RESOLVERS
    // ─────────────────────────────────────────────────────

    private function resolveRegion(int $ukw): ?int
    {
        return isset($this->regions[$ukw]) ? $ukw : null;
    }

    private function resolveRegency(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }

        $key = $this->normalizeGeoName($raw);

        // Exact
        if (isset($this->regencies[$key])) {
            return $this->regencies[$key];
        }

        // Partial: find first regency whose key contains $key
        foreach ($this->regencies as $dbKey => $rid) {
            if (str_contains($dbKey, $key) || str_contains($key, $dbKey)) {
                return $rid;
            }
        }

        return null;
    }

    private function resolveEmployee(string $picName): ?int
    {
        if ($picName === '') {
            return null;
        }

        $lower = strtolower($picName);

        // Exact
        if (isset($this->employees[$lower])) {
            return $this->employees[$lower];
        }

        // Partial: CSV name may have honorific suffix (", S.E")
        $base = preg_replace('/,?\s+[Ss]\..+$/', '', $lower); // strip ", S.E" etc.
        if (isset($this->employees[$base])) {
            return $this->employees[$base];
        }

        // Fuzzy: DB name starts with CSV base
        foreach ($this->employees as $dbName => $eid) {
            if (str_starts_with($dbName, $base) || str_starts_with($base, $dbName)) {
                return $eid;
            }
        }

        return null;
    }

    private function resolvePosition(string $bidang, string $jabatan): ?int
    {
        // Use Bidang first (more structured), fall back to Jabatan
        $candidates = array_filter([$bidang, $jabatan]);

        foreach ($candidates as $raw) {
            $lower = strtolower($raw);

            // Translate via jabatanMap
            $translated = $this->jabatanMap[$lower] ?? $raw;
            if ($translated === '') {
                continue;
            } // explicitly mapped to empty → skip

            $lowerTranslated = strtolower($translated);

            // Exact
            if (isset($this->positions[$lowerTranslated])) {
                return $this->positions[$lowerTranslated];
            }

            // Partial
            foreach ($this->positions as $dbName => $pid) {
                if (str_contains($dbName, $lowerTranslated) || str_contains($lowerTranslated, $dbName)) {
                    return $pid;
                }
            }
        }

        return null;
    }

    private function resolveOpd(string $instansi, ?int $regencyId): ?int
    {
        if ($instansi === '' || $regencyId === null) {
            return null;
        }

        $key = $regencyId.'|'.strtolower($instansi);
        if (isset($this->opds[$key])) {
            return $this->opds[$key];
        }

        // Partial match within same regency
        $prefix = $regencyId.'|';
        $lower = strtolower($instansi);
        foreach ($this->opds as $dbKey => $oid) {
            if (! str_starts_with($dbKey, $prefix)) {
                continue;
            }
            $dbName = substr($dbKey, \strlen($prefix));
            if (str_contains($dbName, $lower) || str_contains($lower, $dbName)) {
                return $oid;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────
    // MAPPERS
    // ─────────────────────────────────────────────────────

    private function mapSumber(string $raw): string
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'wilayah') || str_contains($lower, 'sds wilayah')) {
            return 'SDS Wilayah';
        }
        if (str_contains($lower, 'referal') || str_contains($lower, 'referral')) {
            return 'Referal';
        }
        if (str_contains($lower, 'visit') || str_contains($lower, 'pitching')) {
            return 'Visit/ Pitching';
        }
        if (str_contains($lower, 'sosial') || str_contains($lower, 'medsos')) {
            return 'Media Sosial Instansi';
        }
        if (str_contains($lower, 'service') || str_contains($lower, 'customer')) {
            return 'Customer Service';
        }

        return 'SDS Umum';
    }

    private function mapKategori(string $raw): string
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'kementerian') || str_contains($lower, 'lembaga')) {
            return 'Kementerian / Lembaga';
        }
        if (
            str_contains($lower, 'pemda') ||
            str_contains($lower, 'pemerintah daerah') ||
            str_contains($lower, 'dinas') ||
            str_contains($lower, 'kabupaten') ||
            str_contains($lower, 'provinsi')
        ) {
            return 'Pemerintah Daerah';
        }

        return 'Non Pemerintah';
    }

    // ─────────────────────────────────────────────────────
    // SANITIZERS & HELPERS
    // ─────────────────────────────────────────────────────

    private function sanitizePhone(string $raw): ?string
    {
        if ($raw === '' || str_contains($raw, 'null') || str_contains($raw, '—')) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (\strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '08')) {
            return '+628'.substr($digits, 2);
        }
        if (str_starts_with($digits, '8')) {
            return '+628'.substr($digits, 1);
        }
        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    private function sanitizeEmail(string $raw): ?string
    {
        if (str_contains($raw, '<span') || str_contains($raw, '—') || $raw === '') {
            return null;
        }

        $email = trim(strip_tags($raw));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function parseDate(string $raw, string $fallback): string
    {
        if ($raw === '') {
            return $fallback;
        }

        $parsed = strtotime($raw);

        return $parsed !== false
            ? date('Y-m-d H:i:s', $parsed)
            : $fallback;
    }

    private function normalizeGeoName(string $name): string
    {
        $prefixes = ['kabupaten', 'kab.', 'kota', 'provinsi', 'prov.', 'kab ', 'prov '];
        $lower = strtolower(trim($name));

        foreach ($prefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                $lower = ltrim(substr($lower, \strlen($prefix)));
                break;
            }
        }

        return trim($lower);
    }
}
