<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds cards table from rencanapitching.csv.
 *
 * CSV columns (0-based):
 *  0   ID                        → history_logs.rencana_pitching_id
 *  1   Tahun                     → fiscal_year
 *  2   Provinsi                  → (geo context / ignored)
 *  3   Kabupaten                 → (geo context / ignored)
 *  4   OPD                       → title fallback
 *  5   Nama Kontak               → contact_id (name lookup)
 *  6   HP Kontak                 → (ignored)
 *  7   PMO                       → employee_id (name lookup)
 *  8   Produk / Proyeksi Potensi → title + output_id (name lookup)
 *  9   Kategori Produk           → history_logs
 * 10   Sumber Proyeksi           → sumber_proyeksi
 * 11   Anggaran Proyeksi         → nominal_value
 * 12   Tanggal Pitching          → pitching_date + projection_month
 * 13   Pekan                     → projection_week
 * 14   Metode                    → communication_method
 * 15   Anggaran Pitching (Total) → history_logs
 * 16   Detail Anggaran Pitching  → history_logs
 * 17   Status                    → stage_id (Terlaksana / Rencana)
 * 18   Terverifikasi             → manager_verified
 * 19   Hasil Pitching            → status (Positif → keep, Negative → lost)
 * 20   Alasan Negative           → negative_reason
 * 21   Bulan Closing             → (ignored)
 * 22   Pekan Closing             → (ignored)
 * 23   Status Closing            → projection_status + status
 * 24   Link Dokumen              → drive_link
 * 25   Catatan / Hasil           → history_logs
 * 26   Dibuat Pada               → created_at
 */
class RencanaPitchingCardSeeder extends Seeder
{
    private array $stages    = [];  // stage_name → stage_id
    private array $contacts  = [];  // lowercase_name → contact_id
    private array $employees = [];  // lowercase_name → employee_id
    private array $outputs   = [];  // lowercase_output_name → object{id, output_name}

    public function run(): void
    {
        $csvPath = database_path('seeders/jsonlawas/rencanapitching.csv');
        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found: {$csvPath}");
            return;
        }

        $this->command->info('Pre-loading lookup tables...');
        $this->preloadLookups();

        if (empty($this->stages)) {
            $this->command->error('No Pitching pipeline stages found. Run PipelineSeeder first.');
            return;
        }

        $this->command->info('Processing rencanapitching.csv...');

        $file     = fopen($csvPath, 'r');
        fgetcsv($file, 0, ',', '"', '\\'); // skip header

        $batch    = [];
        $inserted = 0;
        $skipped  = 0;
        $now      = now()->toDateTimeString();

        while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            $record = $this->transform($row, $now);

            if ($record === null) {
                $skipped++;
                continue;
            }

            $batch[] = $record;

            if (\count($batch) >= 200) {
                DB::table('cards')->insert($batch);
                $inserted += \count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('cards')->insert($batch);
            $inserted += \count($batch);
        }

        fclose($file);
        $this->command->info("Done — Inserted: {$inserted} | Skipped: {$skipped}");
    }

    // ─────────────────────────────────────────────────────
    // PRE-LOAD
    // ─────────────────────────────────────────────────────

    private function preloadLookups(): void
    {
        $profile = DB::table('pipeline_profiles')
            ->where('pipeline_name', 'Pitching')
            ->where('is_active', true)
            ->first();

        if ($profile) {
            $this->stages = DB::table('pipeline_stages')
                ->where('pipeline_profile_id', $profile->id)
                ->pluck('id', 'stage_name')
                ->toArray();
            $this->command->line('Stages: ' . implode(', ', array_keys($this->stages)));
        }

        DB::table('contacts')
            ->select('id', 'name')
            ->orderBy('id')
            ->each(function ($c) {
                $this->contacts[strtolower(trim($c->name))] = $c->id;
            });

        DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->select('employees.id as emp_id', 'users.name as uname')
            ->orderBy('employees.id')
            ->each(function ($e) {
                $this->employees[strtolower(trim($e->uname))] = $e->emp_id;
            });

        DB::table('outputs')
            ->select('id', 'output_name', 'generated_code')
            ->orderBy('id')
            ->each(function ($o) {
                $this->outputs[strtolower(trim($o->output_name))] = $o;
                if ($o->generated_code) {
                    $this->outputs[strtolower(trim($o->generated_code))] = $o;
                }
            });
    }

    // ─────────────────────────────────────────────────────
    // TRANSFORM
    // ─────────────────────────────────────────────────────

    private function transform(array $r, string $now): ?array
    {
        $rencanaId = isset($r[0]) ? (int) trim($r[0]) : 0;
        if ($rencanaId === 0) return null;

        $opd     = trim($r[4]  ?? '');
        $kontak  = trim($r[5]  ?? '');
        $produk  = trim($r[8]  ?? '');

        if ($opd === '' && $kontak === '' && $produk === '') return null;

        $output    = $this->resolveOutput($produk);
        $contactId = $this->resolveContact($kontak);
        $empId     = $this->resolveEmployee(trim($r[7] ?? ''));
        $status    = $this->resolveStatus($r);
        $stageId   = $this->resolveStageId($r);

        $title = $output?->output_name
            ?? ($produk !== '' ? $produk : null)
            ?? ($opd    !== '' ? $opd    : null)
            ?? $kontak;

        $tanggalPitching = trim($r[12] ?? '');
        $projectionMonth = $tanggalPitching
            ? date('Y-m-01 07:00:00', strtotime($tanggalPitching))
            : null;

        $historyLogs = array_filter([
            'rencana_pitching_id'     => (string) $rencanaId,
            'kategori_produk'         => trim($r[9]  ?? '') ?: null,
            'anggaran_pitching_total' => trim($r[15] ?? '') ?: null,
            'detail_anggaran'         => trim($r[16] ?? '') ?: null,
            'catatan'                 => trim($r[25] ?? '') ?: null,
        ]);

        $createdAt = trim($r[26] ?? '') ?: $now;

        return [
            'title'                  => $title,
            'status'                 => $status,
            'stage_id'               => $stageId,
            'employee_id'            => $empId,
            'contact_id'             => $contactId,
            'output_id'              => $output?->id,
            'nominal_value'          => $this->resolveNominal(trim($r[11] ?? '')),
            'fiscal_year'            => (int) (trim($r[1] ?? '') ?: date('Y')),
            'sumber_proyeksi'        => trim($r[10] ?? '') ?: null,
            'detail_sumber_proyeksi' => null,
            'pitching_date'          => $tanggalPitching ?: null,
            'communication_method'   => trim($r[14] ?? '') ?: null,
            'projection_month'       => $projectionMonth,
            'projection_week'        => trim($r[13] ?? '') ?: null,
            'projection_status'      => trim($r[23] ?? '') ?: (trim($r[17] ?? '') ?: null),
            'drive_link'             => trim($r[24] ?? '') ?: null,
            'negative_reason'        => trim($r[20] ?? '') ?: null,
            'manager_verified'       => strtolower(trim($r[18] ?? '')) === 'ya',
            's3_document_path'       => null,
            'history_logs'           => json_encode($historyLogs ?: new \stdClass()),
            'follow_ups'             => json_encode([]),
            'archived_at'            => $status === 'lost' ? $createdAt : null,
            'created_at'             => $createdAt,
            'updated_at'             => $now,
        ];
    }

    // ─────────────────────────────────────────────────────
    // RESOLVERS
    // ─────────────────────────────────────────────────────

    private function resolveOutput(string $produk): ?object
    {
        if ($produk === '') return null;

        $lower = strtolower($produk);

        if (isset($this->outputs[$lower])) {
            return (object) $this->outputs[$lower];
        }

        foreach ($this->outputs as $key => $o) {
            if (str_contains($lower, $key) || str_contains($key, $lower)) {
                return (object) $o;
            }
        }

        return null;
    }

    private function resolveContact(string $name): ?int
    {
        if ($name === '') return null;

        $lower = strtolower($name);
        if (isset($this->contacts[$lower])) return $this->contacts[$lower];

        $base = preg_replace('/,?\s+[Ss]\..+$/', '', $lower);
        if (isset($this->contacts[$base])) return $this->contacts[$base];

        foreach ($this->contacts as $cName => $cId) {
            if (str_contains($cName, $lower) || str_contains($lower, $cName)) {
                return $cId;
            }
        }

        return null;
    }

    private function resolveEmployee(string $pmoName): ?int
    {
        if ($pmoName === '') return null;

        $lower = strtolower($pmoName);
        if (isset($this->employees[$lower])) return $this->employees[$lower];

        $base = preg_replace('/,?\s+[Ss]\..+$/', '', $lower);
        if (isset($this->employees[$base])) return $this->employees[$base];

        foreach ($this->employees as $eName => $eId) {
            if (str_starts_with($eName, $base) || str_starts_with($base, $eName)) {
                return $eId;
            }
        }

        return null;
    }

    private function resolveStatus(array $r): string
    {
        $statusClosing = strtolower(trim($r[23] ?? ''));
        $hasilPitching = strtolower(trim($r[19] ?? ''));

        if (str_contains($statusClosing, 'won'))     return 'won';
        if (str_contains($statusClosing, 'optimis')) return 'hot';
        if (str_contains($statusClosing, 'moderate')) return 'warm';
        if ($hasilPitching === 'negative')           return 'lost';

        return 'Active';
    }

    private function resolveStageId(array $r): int
    {
        $statusCsv     = strtolower(trim($r[17] ?? ''));  // Terlaksana / Rencana
        $hasilPitching = strtolower(trim($r[19] ?? ''));  // Positif / Negative

        if ($statusCsv === 'terlaksana' && $hasilPitching === 'negative') {
            return $this->stages['Tidak Terlaksana'] ?? 3;
        }

        if ($statusCsv === 'terlaksana') {
            return $this->stages['Terlaksana'] ?? 2;
        }

        return $this->stages['Rencana'] ?? 1;
    }

    private function resolveNominal(string $raw): float
    {
        if ($raw === '') return 0.0;
        $cleaned = preg_replace('/[^\d]/', '', $raw);
        return ($cleaned !== '' && $cleaned !== '0') ? (float) $cleaned : 0.0;
    }
}
