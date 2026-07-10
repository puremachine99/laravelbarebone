<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds cards table from proyeksi.csv (partnership export).
 *
 * CSV columns (0-based) — updated structure:
 *  0   ID                 → history_logs.partnership_prediksi_id
 *  1   Tahun              → fiscal_year
 *  2   Jenis              → communication_method / history_logs.jenis
 *  3   UKW                → history_logs.id_ukw
 *  4   PMO                → employee_id (name lookup)
 *  5   Provinsi           → (ignored)
 *  6   Kabupaten          → (ignored)
 *  7   Instansi           → title fallback
 *  8   Nama Kontak        → contact_id (name lookup)
 *  9   Jabatan Kontak     → (ignored)
 * 10   Bidang             → (ignored)
 * 11   Proyeksi Kegiatan  → title + output_id (name lookup)
 * 12   Kategori Kegiatan  → history_logs.kategori_kegiatan
 * 13   Bulan Proyeksi     → projection_month
 * 14   Pekan              → projection_week
 * 15   Status             → status text (Warm/Hot/Optimis/Moderate/Closing/Deal/Lost)
 * 16   Nominal            → nominal_value
 * 17   Tgl Won            → (ignored)
 * 18   BANT Budget        → history_logs.bant.budget
 * 19   BANT Authority     → history_logs.bant.authority
 * 20   BANT Needs         → history_logs.bant.needs
 * 21   BANT Timeline      → history_logs.bant.timeline
 * 22   Verifikasi Manager → manager_verified
 * 23   Tanggal Closing    → archived_at (if lost) / created_at
 * 24   Link BA            → drive_link
 * 25   Tanggal Pitching   → pitching_date
 * 26   Follow Up          → follow_ups (JSON array from CSV)
 */
class PrediksiCardSeeder extends Seeder
{
    private array $stages = [];   // stage_name → stage_id

    private array $contacts = [];   // lowercase_name → contact_id

    private array $employees = [];   // lowercase_user_name → employee_id

    private array $outputs = [];   // lowercase_output_name → object{id, output_name}

    public function run(): void
    {
        $csvPath = database_path('seeders/jsonlawas/proyeksi.csv');
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

        $this->command->info('Processing proyeksi.csv...');

        $file = fopen($csvPath, 'r');
        fgetcsv($file, 0, ',', '"', '\\'); // skip header

        $batch = [];
        $inserted = 0;
        $skipped = 0;
        $now = now()->toDateTimeString();

        while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            $record = $this->transform($row, $now);

            if ($record === null) {
                $skipped++;

                continue;
            }

            $batch[] = $record;

            if (\count($batch) >= 200) {
                DB::table('pmo_cards')->insert($batch);
                $inserted += \count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('pmo_cards')->insert($batch);
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
        $profile = DB::table('pmo_pipeline_profiles')
            ->where('pipeline_name', 'Pitching')
            ->where('is_active', true)
            ->first();

        if ($profile) {
            $this->stages = DB::table('pmo_pipeline_stages')
                ->where('pipeline_profile_id', $profile->id)
                ->pluck('id', 'stage_name')
                ->toArray();
            $this->command->line('Stages: '.implode(', ', array_keys($this->stages)));
        }

        DB::table('pmo_contacts')
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

        DB::table('pmo_outputs')
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
        $prediksiId = isset($r[0]) ? (int) trim($r[0]) : 0;
        if ($prediksiId === 0) {
            return null;
        }

        $kegiatan = trim($r[11] ?? '');
        $instansi = trim($r[7] ?? '');
        $kontak = trim($r[8] ?? '');

        if ($kegiatan === '' && $instansi === '' && $kontak === '') {
            return null;
        }

        $output = $this->resolveOutput($kegiatan);
        $contactId = $this->resolveContact($kontak);
        $empId = $this->resolveEmployee(trim($r[4] ?? ''));
        $status = $this->resolveStatus($r);
        $stageId = $this->resolveStageId($status);
        $nominal = $this->resolveNominal(trim($r[16] ?? ''));

        $title = $output?->output_name
            ?? ($kegiatan !== '' ? $kegiatan : null)
            ?? ($instansi !== '' ? $instansi : null)
            ?? $kontak;

        // col 23: Tanggal Closing, col 25: Tanggal Pitching
        $tanggalClosing = $this->parseDate(trim($r[23] ?? ''));
        $tanggalPitching = $this->parseDate(trim($r[25] ?? ''));

        // col 18–21: BANT
        $bant = array_filter([
            'budget' => trim($r[18] ?? '') ?: null,
            'authority' => trim($r[19] ?? '') ?: null,
            'needs' => trim($r[20] ?? '') ?: null,
            'timeline' => trim($r[21] ?? '') ?: null,
        ]);

        $historyLogs = array_filter([
            'partnership_prediksi_id' => (string) $prediksiId,
            'jenis' => trim($r[2] ?? '') ?: null,
            'id_ukw' => trim($r[3] ?? '') ?: null,
            'kategori_kegiatan' => trim($r[12] ?? '') ?: null,
            'bant' => empty($bant) ? null : $bant,
        ]);

        // col 26: Follow Up — JSON array encoded with "" doubling (RFC 4180), fgetcsv handles it
        $followUpRaw = trim($r[26] ?? '');
        if ($followUpRaw !== '') {
            $decoded = json_decode($followUpRaw, true);
            $followUps = is_array($decoded) ? $decoded : [['note' => $followUpRaw, 'created_at' => $tanggalClosing ?? $now]];
        } else {
            $followUps = [];
        }

        return [
            'title' => $title,
            'status' => $status,
            'stage_id' => $stageId,
            'employee_id' => $empId,
            'contact_id' => $contactId,
            'output_id' => $output?->id,
            'nominal_value' => $nominal,
            'fiscal_year' => (int) (trim($r[1] ?? '') ?: 2026),
            'sumber_proyeksi' => null,
            'communication_method' => str_contains(strtolower(trim($r[2] ?? '')), 'offline') ? 'Offline' : 'Online',
            'projection_month' => $this->buildProjectionDate(trim($r[1] ?? ''), trim($r[13] ?? ''), trim($r[14] ?? '')),
            'projection_week' => trim($r[14] ?? '') ?: null,
            'projection_status' => trim($r[15] ?? '') ?: 'Proyeksi',
            'pitching_date' => $tanggalPitching,
            'drive_link' => trim($r[24] ?? '') ?: null,
            'manager_verified' => strtolower(trim($r[22] ?? '')) === 'ya',
            'archived_at' => $status === 'lost' ? ($tanggalClosing ?? $now) : null,
            'history_logs' => json_encode($historyLogs),
            'follow_ups' => json_encode($followUps),
            'created_at' => $tanggalClosing ?? $now,
            'updated_at' => $now,
        ];
    }

    // ─────────────────────────────────────────────────────
    // RESOLVERS
    // ─────────────────────────────────────────────────────

    private function resolveOutput(string $kegiatan): ?object
    {
        if ($kegiatan === '') {
            return null;
        }

        $lower = strtolower($kegiatan);

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
        if ($name === '') {
            return null;
        }

        $lower = strtolower($name);
        if (isset($this->contacts[$lower])) {
            return $this->contacts[$lower];
        }

        $base = preg_replace('/,?\s+[Ss]\..+$/', '', $lower);
        if (isset($this->contacts[$base])) {
            return $this->contacts[$base];
        }

        foreach ($this->contacts as $cName => $cId) {
            if (str_contains($cName, $lower) || str_contains($lower, $cName)) {
                return $cId;
            }
        }

        return null;
    }

    private function resolveEmployee(string $pmoName): ?int
    {
        if ($pmoName === '') {
            return null;
        }

        $lower = strtolower($pmoName);
        if (isset($this->employees[$lower])) {
            return $this->employees[$lower];
        }

        $base = preg_replace('/,?\s+[Ss]\..+$/', '', $lower);
        if (isset($this->employees[$base])) {
            return $this->employees[$base];
        }

        foreach ($this->employees as $eName => $eId) {
            if (str_starts_with($eName, $base) || str_starts_with($base, $eName)) {
                return $eId;
            }
        }

        return null;
    }

    private function resolveStatus(array $r): string
    {
        // Status is now purely col 15 text (confirmation columns removed in updated CSV)
        return match (strtolower(trim($r[15] ?? ''))) {
            'closing', 'deal', 'won' => 'won',
            'optimis', 'hot' => 'hot',
            'moderate', 'warm' => 'warm',
            'lost' => 'lost',
            default => 'Active',
        };
    }

    private function resolveStageId(string $status): int
    {
        return match ($status) {
            'won' => $this->stages['Terlaksana'] ?? 2,
            'lost' => $this->stages['Tidak Terlaksana'] ?? 3,
            default => $this->stages['Rencana'] ?? 1,
        };
    }

    private function buildProjectionDate(string $year, string $month, string $week): ?string
    {
        if ($month === '') {
            return null;
        }
        $y = (int) ($year ?: 2026);

        $monthMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5,
            'june' => 6, 'july' => 7, 'august' => 8, 'october' => 10, 'december' => 12,
        ];

        $mLower = strtolower(trim($month));
        $m = isset($monthMap[$mLower]) ? $monthMap[$mLower] : (int) $month;

        if ($m < 1 || $m > 12) {
            return null;
        }

        $w = max(1, (int) ($week ?: 1));
        $day = ($w - 1) * 7 + 1;
        $lastDay = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        $day = min($day, $lastDay);

        return \sprintf('%04d-%02d-%02d 07:00:00', $y, $m, $day);
    }

    private function parseDate(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        // Already Y-m-d or Y-m-d H:i:s
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return $raw;
        }

        // d-m-Y or d/m/Y
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d 07:00:00', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // d-m-Y H:i:s
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})\s+\d{2}:\d{2}/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d 07:00:00', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function resolveNominal(string $raw): float
    {
        if ($raw === '') {
            return 0.0;
        }
        $cleaned = preg_replace('/[^\d]/', '', $raw);

        return ($cleaned !== '' && $cleaned !== '0') ? (float) $cleaned : 0.0;
    }
}
