<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sync:cards
    {--year= : Fiscal year to import (default: all). When set, only that year\'s cards are replaced.}
    {--dry-run : Show counts without modifying data}
    {--skip-truncate : Skip deleting existing cards (for additive runs)}')]
#[Description('Migrate rencana_pitchings → cards. Uses rencana_pitchings as primary source, enriched with prediksis status flags.')]
class SyncPartnershipCards extends Command
{
    private string $ext = 'mysql_partnership';

    private array $stages = [];    // stage_name → id

    private array $employees = []; // partner_user_id → smartboard_employee_id

    private array $contacts = [];  // partnership_contact_id → smartboard_contact_id

    private array $outputs = [];   // lowercase_name/code → output object

    public function handle(): int
    {
        if (! config("database.connections.{$this->ext}")) {
            $this->error("Connection '{$this->ext}' not configured.");

            return Command::FAILURE;
        }

        try {
            DB::connection($this->ext)->getPdo();
        } catch (\Exception $e) {
            $this->error('Cannot connect to Partnership DB: '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Pre-loading lookup tables...');
        $this->preloadLookups();

        if (empty($this->stages)) {
            $this->error('No Pitching pipeline stages found. Run PipelineSeeder first.');

            return Command::FAILURE;
        }

        $year = $this->option('year');

        // ── Dry run ────────────────────────────────────────
        if ($this->option('dry-run')) {
            $total = DB::connection($this->ext)
                ->table('rencana_pitchings')
                ->whereNull('deleted_at')
                ->when($year, fn ($q) => $q->where('tahun', $year))
                ->count();

            $this->table(['Source', 'Rows'], [
                ['rencana_pitchings'.($year ? " (tahun={$year})" : ' (all years)'), number_format($total)],
                ['Employees matched', count($this->employees).' / '.DB::connection($this->ext)->table('users')->count()],
                ['Contacts preloaded', count($this->contacts)],
                ['Stages', implode(', ', array_keys($this->stages))],
            ]);

            return Command::SUCCESS;
        }

        // ── Clear existing cards for the scope ────────────
        if (! $this->option('skip-truncate')) {
            if ($year) {
                $count = DB::table('pmo_cards')->where('fiscal_year', (int) $year)->count();
                $this->warn("Deleting {$count} existing cards for fiscal_year={$year}...");
                DB::table('pmo_cards')->where('fiscal_year', (int) $year)->delete();
            } else {
                if (! $this->confirm('No --year set. This will DELETE ALL cards. Continue?', false)) {
                    return Command::FAILURE;
                }
                $this->warn('Deleting all cards...');
                DB::statement('DELETE FROM pmo_cards');

                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT setval('pmo_cards_id_seq', 1, false)");
                }
            }
        }

        // ── Migrate ────────────────────────────────────────
        $this->syncFromRencanaPitching($year);

        $scope = $year
            ? DB::table('pmo_cards')->where('fiscal_year', (int) $year)->count()
            : DB::table('pmo_cards')->count();

        $this->newLine();
        $this->info('Migration complete. Cards in scope: '.$scope);

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────
    // PRE-LOAD LOOKUPS
    // ─────────────────────────────────────────────────────

    private function preloadLookups(): void
    {
        // Pipeline stages for Pitching
        $profile = DB::table('pmo_pipeline_profiles')
            ->where('pipeline_name', 'Pitching')
            ->where('is_active', true)
            ->first();

        if ($profile) {
            $this->stages = DB::table('pmo_pipeline_stages')
                ->where('pipeline_profile_id', $profile->id)
                ->where('is_active', true)
                ->pluck('id', 'stage_name')
                ->toArray();
        }

        // Employee map: partnership user_id → smartboard employee_id
        // Strip gelar then match on smartboard users.name (case-insensitive)
        DB::connection($this->ext)->table('users')->select('id', 'nama')->orderBy('id')->each(function ($u) {
            $clean = strtolower(trim(preg_replace('/,?\s*[A-Z][A-Za-z]*\..*$/', '', $u->nama)));

            if (strlen($clean) < 3) {
                return;
            }

            $emp = Employee::whereHas('user', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$clean]))->first()
                ?? Employee::whereHas('user', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$clean}%"]))->first();

            if ($emp) {
                $this->employees[(string) $u->id] = $emp->id;
            }
        });

        $this->line('Employees matched: '.count($this->employees));

        // Contacts: partnership_contact_id → smartboard contact_id
        DB::table('pmo_contacts')
            ->whereNotNull('partnership_contact_id')
            ->pluck('id', 'partnership_contact_id')
            ->each(function ($id, $pid) {
                $this->contacts[(int) $pid] = $id;
            });

        $this->line('Contacts preloaded: '.count($this->contacts));

        // Outputs: indexed by lowercase output_name and generated_code
        DB::table('pmo_outputs')->select('id', 'output_name', 'generated_code')->orderBy('id')->each(function ($o) {
            $key = strtolower(trim($o->output_name));
            $this->outputs[$key] = $o;

            if ($o->generated_code) {
                $this->outputs[strtolower(trim($o->generated_code))] = $o;
            }
        });
    }

    // ─────────────────────────────────────────────────────
    // SYNC: rencana_pitchings → cards
    //   LEFT JOIN prediksis to enrich with status flags
    // ─────────────────────────────────────────────────────

    private function syncFromRencanaPitching(?string $year): void
    {
        $this->info('[rencana_pitchings] Fetching...');

        $query = DB::connection($this->ext)
            ->table('rencana_pitchings as rp')
            ->leftJoin('prediksis as p', 'p.rencana_pitching_id', '=', 'rp.id')
            ->leftJoin('kontaks', 'kontaks.id', '=', 'rp.kontak_id')
            ->select(
                // rencana_pitching base fields
                'rp.id',
                'rp.tahun',
                'rp.created_by',
                'rp.kontak_id',
                'rp.proyeksi_potensi',
                'rp.sumber_proyeksi',
                'rp.anggaran_proyeksi',
                'rp.tanggal_pitching',
                'rp.metode',
                'rp.hasil_pitching',
                'rp.alasan_negative',
                'rp.status_pelaksanaan',
                'rp.is_verified',
                'rp.link_dokumen',
                'rp.bulan_closing',
                'rp.pekan',
                'rp.created_at',
                // contact name for fallback title
                'kontaks.kontak_nama as kontak_name',
                // prediksi enrichment (status flags + extra fields)
                'p.id as prediksi_id',
                'p.closing',
                'p.deal',
                'p.optimis',
                'p.moderate',
                'p.lost',
                'p.proyeksi as prediksi_proyeksi',
                'p.ba as prediksi_ba',
                'p.verifikasi_manager',
            )
            ->whereNull('rp.deleted_at')
            ->when($year, fn ($q) => $q->where('rp.tahun', $year));

        $total = $query->count();
        $this->line("Found {$total} rows.");

        if ($total === 0) {
            $this->info('[rencana_pitchings] No rows found, skipping.');

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $skipped = 0;
        $batch = [];
        $now = now()->toDateTimeString();

        $query->orderBy('rp.id')->chunk(300, function ($rows) use ($bar, &$inserted, &$skipped, &$batch, $now) {
            foreach ($rows as $row) {
                $record = $this->transformRencana($row, $now);

                if ($record === null) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $batch[] = $record;
                $inserted++;
                $bar->advance();

                if (count($batch) >= 300) {
                    DB::table('pmo_cards')->insert($batch);
                    $batch = [];
                }
            }
        });

        if (! empty($batch)) {
            DB::table('pmo_cards')->insert($batch);
        }

        $bar->finish();
        $this->newLine();
        $this->info("[rencana_pitchings] Inserted: {$inserted} | Skipped: {$skipped}");
    }

    // ─────────────────────────────────────────────────────
    // TRANSFORM: rencana_pitching (with optional prediksi enrichment) → card
    // ─────────────────────────────────────────────────────

    private function transformRencana(object $row, string $now): ?array
    {
        $potensi = trim($row->proyeksi_potensi ?? '');
        $kontakId = (int) ($row->kontak_id ?? 0);

        // Skip rows with no meaningful data
        if ($potensi === '' && $kontakId === 0) {
            return null;
        }

        $output = $this->resolveOutput($potensi);
        $title = mb_substr(
            $output?->output_name ?? ($potensi !== '' ? $potensi : ($row->kontak_name ?? 'Pitching')),
            0,
            255
        );

        // Status: prediksi flags take priority over hasil_pitching
        $hasilRaw = $row->hasil_pitching ?? null;
        $status = $this->resolveStatusFromRencana($row, $hasilRaw);
        $stageId = $this->resolveStageId($status, $row->status_pelaksanaan ?? null);

        $year = (int) ($row->tahun ?: date('Y'));

        $bulan = ! empty($row->bulan_closing) ? (int) $row->bulan_closing : 0;
        $projectionMonth = $bulan > 0 ? sprintf('%04d-%02d', $year, $bulan) : null;
        $projectionWeek = ! empty($row->pekan) ? 'Pekan '.$row->pekan : null;

        $negativeReason = null;
        if ($status === 'lost' && ! empty($row->alasan_negative)) {
            $negativeReason = $this->normalizeNegativeReason($row->alasan_negative);
        }

        // Drive link: prefer rp.link_dokumen, fallback prediksi.ba
        $driveRaw = $row->link_dokumen ?: $row->prediksi_ba ?: null;
        $driveLink = $driveRaw ? mb_substr($driveRaw, 0, 255) : null;

        // Verified: rp.is_verified or prediksi.verifikasi_manager
        $verified = (bool) ($row->is_verified ?? false)
            || (bool) (int) ($row->verifikasi_manager ?? 0);

        // Nominal: rp.anggaran_proyeksi, fallback prediksi.proyeksi
        $nominal = (float) ($row->anggaran_proyeksi ?? 0);
        if ($nominal === 0.0 && ! empty($row->prediksi_proyeksi)) {
            $nominal = (float) $row->prediksi_proyeksi;
        }

        return [
            'title' => $title,
            'status' => $status,
            'stage_id' => $stageId,
            'employee_id' => $this->employees[(string) $row->created_by] ?? null,
            'contact_id' => $kontakId > 0 ? ($this->contacts[$kontakId] ?? null) : null,
            'output_id' => $output?->id,
            'nominal_value' => $nominal,
            'fiscal_year' => $year,
            'sumber_proyeksi' => $this->normalizeSumber($row->sumber_proyeksi ?? ''),
            'communication_method' => mb_substr($row->metode ?: 'Online', 0, 255),
            'pitching_date' => $row->tanggal_pitching ?: null,
            'projection_month' => $projectionMonth,
            'projection_week' => $projectionWeek,
            'drive_link' => $driveLink,
            'negative_reason' => $negativeReason,
            'manager_verified' => $verified,
            'history_logs' => json_encode([
                'rencana_pitching_id' => $row->id,
                'prediksi_id' => $row->prediksi_id,
            ]),
            'follow_ups' => json_encode([]),
            'created_at' => $row->created_at ?? $now,
            'updated_at' => $now,
        ];
    }

    // ─────────────────────────────────────────────────────
    // RESOLVERS
    // ─────────────────────────────────────────────────────

    private function resolveStatusFromRencana(object $row, ?string $hasilRaw): string
    {
        // Prediksi flags take priority
        if (! empty($row->closing) || ! empty($row->deal)) {
            return 'won';
        }

        if (! empty($row->optimis)) {
            return 'hot';
        }

        if (! empty($row->moderate)) {
            return 'warm';
        }

        if (! empty($row->lost)) {
            return 'lost';
        }

        // Fallback: rencana_pitching hasil_pitching
        return match (true) {
            $hasilRaw === 'positive' && $row->status_pelaksanaan === 'terlaksana' => 'hot',
            $hasilRaw === 'negative' => 'lost',
            $row->status_pelaksanaan === 'terlaksana' => 'hot',
            $row->status_pelaksanaan === 'tidak_terlaksana' => 'lost',
            default => 'Active',
        };
    }

    private function resolveStageId(string $status, ?string $pelaksanaan): int
    {
        if ($pelaksanaan === 'terlaksana') {
            return $this->stages['Terlaksana'] ?? (array_values($this->stages)[1] ?? 1);
        }

        if ($pelaksanaan === 'tidak_terlaksana') {
            return $this->stages['Tidak Terlaksana'] ?? (array_values($this->stages)[2] ?? 1);
        }

        return match ($status) {
            'won', 'hot', 'warm' => $this->stages['Terlaksana'] ?? (array_values($this->stages)[1] ?? 1),
            'lost' => $this->stages['Tidak Terlaksana'] ?? (array_values($this->stages)[2] ?? 1),
            default => $this->stages['Rencana'] ?? (array_values($this->stages)[0] ?? 1),
        };
    }

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

    private function normalizeSumber(string $raw): ?string
    {
        $upper = strtoupper(trim($raw));

        if (str_contains($upper, 'SIRUP')) {
            return 'SIRUP';
        }

        if (str_contains($upper, 'SIPD')) {
            return 'SIPD';
        }

        if (str_contains($upper, 'INAPROC')) {
            return 'INAPROC';
        }

        if (str_contains($upper, 'ANALISIS') || str_contains($upper, 'PS')) {
            return 'ANALISIS PS';
        }

        if (str_contains($upper, 'MITRA') || str_contains($upper, 'PERMINTAAN')) {
            return 'PERMINTAAN MITRA';
        }

        return $raw ? mb_substr($raw, 0, 255) : null;
    }

    private function normalizeNegativeReason(string $raw): string
    {
        $lower = strtolower(trim($raw));

        if (str_contains($lower, 'budget') || str_contains($lower, 'anggaran')) {
            return 'No Budget';
        }

        if (str_contains($lower, 'authority') || str_contains($lower, 'wewenang')) {
            return 'No Authority';
        }

        if (str_contains($lower, 'need') || str_contains($lower, 'butuh')) {
            return 'No Need';
        }

        if (str_contains($lower, 'competitor') || str_contains($lower, 'kalah') || str_contains($lower, 'kompetitor')) {
            return 'Lost to Competitor';
        }

        return 'No Budget';
    }
}
