<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('sync:pitchings')]
#[Description('ETL: Import 2026 pitching pipeline from Partnership DB into Smartboard cards')]
class SyncPitching2026ToCards extends Command
{
    public function handle(): int
    {
        $source = 'mysql_partnership';

        if (! config("database.connections.{$source}")) {
            $this->error("Source connection '{$source}' is not configured in config/database.php");

            return Command::FAILURE;
        }

        $this->info('Testing source connection...');

        try {
            DB::connection($source)->getPdo();
        } catch (\Exception $e) {
            $this->error('Cannot connect to Partnership DB: '.$e->getMessage());

            return Command::FAILURE;
        }

        foreach (['prediksis', 'rencana_pitchings'] as $table) {
            if (! Schema::connection($source)->hasTable($table)) {
                $this->error("Required source table '{$table}' not found in Partnership DB.");

                return Command::FAILURE;
            }
        }

        $this->info('Loading pipeline stages...');
        $profile = DB::table('pipeline_profiles')
            ->where('pipeline_name', 'Pitching')
            ->where('is_active', true)
            ->first();

        if (! $profile) {
            $this->error('No active Pitching pipeline profile found.');

            return Command::FAILURE;
        }

        $stages = DB::table('pipeline_stages')
            ->where('pipeline_profile_id', $profile->id)
            ->pluck('id', 'stage_name')
            ->toArray();

        foreach (['Rencana', 'Terlaksana', 'Tidak Terlaksana'] as $required) {
            if (! isset($stages[$required])) {
                $this->error("Required stage '{$required}' not found in Pitching pipeline.");

                return Command::FAILURE;
            }
        }

        $this->line('Stages: '.implode(', ', array_keys($stages)));

        // Pre-load pitching rekaps grouped by prediksi ID
        $this->info('Loading pitching rekaps...');
        $pitchingRekaps = DB::connection($source)
            ->table('rekaps')
            ->where('rekap_jenis', 'pitching')
            ->whereIn('rekap_proyeksi', function ($q) use ($source) {
                $q->select('id')->from('prediksis')->where('tahun', '2026');
            })
            ->select('rekap_proyeksi', 'rekap_tgl', 'rekap_terlaksana', 'rekap_tdk_terlaksana')
            ->get()
            ->groupBy('rekap_proyeksi');

        // Pre-load all outputs
        $this->info('Loading outputs...');
        $outputs = DB::table('outputs')
            ->select('id', 'output_name', 'generated_code')
            ->get()
            ->keyBy('generated_code');

        // TRANSLASI ID: Pre-load mapping kontak dari database Smartboard
        // Format hasil: [partnership_kontaks.id => smartboard_contacts.id]
        $this->info('Loading contacts mapping (Partnership kontaks.id => Smartboard contacts.id)...');
        $contactsMap = DB::table('contacts')
            ->whereNotNull('partnership_contact_id')
            ->pluck('id', 'partnership_contact_id');

        // FIX #1: Pre-load bidangs dari Partnership DB untuk fallback resolusi kontak
        // Dibutuhkan ketika prediksis.id_kontak = NULL, sehingga kontak harus di-resolve
        // via prediksis.id_bidang -> bidangs.id_kontak (JSON array) -> kontaks.id pertama
        $this->info('Loading bidangs contact map from Partnership DB (for contact fallback)...');
        $bidangsContactMap = $this->loadBidangsContactMap($source);
        $this->line("  Loaded {$bidangsContactMap->count()} bidangs with contact references.");

        $total = DB::connection($source)
            ->table('prediksis')
            ->where('tahun', '2026')
            ->count();

        if ($total === 0) {
            $this->warn('No prediksis records found for tahun = 2026.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} prediksis records for 2026. Starting ETL...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $failed   = 0;

        DB::connection($source)
            ->table('prediksis as p')
            ->where('p.tahun', '2026')
            ->leftJoin('rencana_pitchings as rp', 'rp.id', '=', 'p.rencana_pitching_id')
            ->select([
                'p.id', 'p.tahun', 'p.jenis', 'p.id_ukw', 'p.id_pmo', 'p.id_provinsi',
                'p.id_kabupaten', 'p.id_instansi', 'p.id_bidang', 'p.id_kontak',
                'p.kegiatan', 'p.pekan', 'p.bulan', 'p.bulan_awal', 'p.bulan_akhir',
                'p.bulan_pencairan', 'p.tgl_bimtek', 'p.sumber_informasi', 'p.sumber',
                'p.nama_instansi', 'p.profiling_market', 'p.tgl_fu', 'p.hasil_fu',
                'p.alasan_closing', 'p.closing', 'p.deal', 'p.optimis', 'p.moderate',
                'p.proyeksi', 'p.lost', 'p.tanggal_closing', 'p.link_acc',
                'p.link_branding', 'p.verifikasi_manager', 'p.monev_proyeksi',
                'p.monev_budget', 'p.monev_authority', 'p.monev_needs', 'p.monev_timeline',
                'p.monev_kontrak', 'p.created_at as p_created_at',
                'rp.tanggal_pitching as rp_tanggal_pitching', 'rp.metode as rp_metode',
                'rp.sumber_proyeksi as rp_sumber_proyeksi', 'rp.anggaran_proyeksi as rp_anggaran_proyeksi',
                'rp.alasan_negative as rp_alasan_negative', 'rp.link_dokumen as rp_link_dokumen',
                'rp.pekan as rp_pekan',
            ])
            ->orderBy('p.id')
            ->chunk(200, function ($rows) use ($stages, $outputs, $pitchingRekaps, $contactsMap, $bidangsContactMap, $bar, &$inserted, &$updated, &$skipped, &$failed) {
                foreach ($rows as $row) {
                    try {
                        $payload = $this->transform($row, $stages, $outputs, $pitchingRekaps, $contactsMap, $bidangsContactMap);

                        if ($payload === null) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        $existing = DB::table('cards')
                            ->whereRaw("history_logs->>'partnership_prediksi_id' = ?", [(string) $row->id])
                            ->first();

                        if ($existing) {
                            DB::table('cards')
                                ->where('id', $existing->id)
                                ->update(array_merge($payload, ['updated_at' => now()]));
                            $updated++;
                        } else {
                            DB::table('cards')->insert(array_merge($payload, [
                                'created_at' => $row->p_created_at ?? now(),
                                'updated_at' => now(),
                            ]));
                            $inserted++;
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        logger()->error("ETL SyncPitching2026 error for prediksi ID {$row->id}: ".$e->getMessage());
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("ETL completed — Inserted: {$inserted} | Updated: {$updated} | Skipped: {$skipped} | Failed: {$failed}");

        if ($failed > 0) {
            $this->warn('Some records failed. Check laravel.log for details.');
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * FIX #1 — Load bidangs.id_kontak dari Partnership DB.
     *
     * bidangs.id_kontak menyimpan JSON array of kontaks.id (e.g. "[23339]" atau "[12, 45, 78]").
     * Kita ambil ID pertama dari array tersebut sebagai kontak utama per bidang.
     *
     * Return format: Collection [bidangs.id => kontaks.id_pertama]
     */
    private function loadBidangsContactMap(string $source): Collection
    {
        return DB::connection($source)
            ->table('bidangs')
            ->whereNotNull('id_kontak')
            ->where('id_kontak', '!=', '')
            ->where('id_kontak', '!=', '[]')
            ->select('id', 'id_kontak')
            ->get()
            ->mapWithKeys(function ($bidang) {
                $firstKontakId = $this->parseFirstKontakId($bidang->id_kontak);

                return [$bidang->id => $firstKontakId];
            })
            ->filter(); // Buang entry null (bidang tanpa kontak valid)
    }

    /**
     * FIX #2 — Parse JSON array id_kontak dan ambil ID pertama yang valid.
     *
     * bidangs.id_kontak bisa berformat:
     *   - JSON array: "[23339]" atau "[19273,7359,12150]"
     *   - Integer string tunggal: "23339" (legacy)
     *   - NULL / string kosong (sudah difilter sebelumnya)
     */
    private function parseFirstKontakId(mixed $idKontak): ?int
    {
        if (empty($idKontak)) {
            return null;
        }

        $raw = trim((string) $idKontak);

        // Format JSON array: "[23339]" atau "[19273,7359]"
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && ! empty($decoded)) {
                $first = array_values(array_filter($decoded))[0] ?? null;

                return $first ? (int) $first : null;
            }

            return null;
        }

        // Format integer tunggal (legacy): "23339"
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * FIX #3 — Resolve contact_id dengan dua strategi:
     *
     * Strategi 1 (utama): prediksis.id_kontak → langsung lookup ke contactsMap
     * Strategi 2 (fallback): prediksis.id_bidang → bidangsContactMap → kontaks.id → contactsMap
     *
     * Ini menangani kasus umum di mana prediksis.id_kontak = NULL karena kontak
     * hanya terdaftar di level bidang (bukan dipilih eksplisit di prediksi).
     */
    private function resolveContactId(object $row, Collection $contactsMap, Collection $bidangsContactMap): ?int
    {
        // Strategi 1: id_kontak langsung di prediksi (paling akurat)
        if (! empty($row->id_kontak)) {
            $smartboardId = $contactsMap->get((int) $row->id_kontak);
            if ($smartboardId !== null) {
                return (int) $smartboardId;
            }
            // id_kontak ada tapi belum tersync ke Smartboard — log dan lanjut ke fallback
            logger()->debug("ETL: prediksi #{$row->id} — id_kontak {$row->id_kontak} not found in Smartboard contacts (not yet synced?).");
        }

        // Strategi 2: fallback via id_bidang -> bidangs.id_kontak -> kontaks.id -> smartboard
        if (! empty($row->id_bidang)) {
            $partnershipKontakId = $bidangsContactMap->get((int) $row->id_bidang);
            if ($partnershipKontakId !== null) {
                $smartboardId = $contactsMap->get($partnershipKontakId);
                if ($smartboardId !== null) {
                    return (int) $smartboardId;
                }
                // Kontak dari bidang belum tersync
                logger()->debug("ETL: prediksi #{$row->id} — bidang fallback kontak #{$partnershipKontakId} not found in Smartboard contacts.");
            }
        }

        return null;
    }

    /**
     * Transform satu row prediksi menjadi payload cards.
     */
    private function transform(object $row, array $stages, Collection $outputs, Collection $pitchingRekaps, Collection $contactsMap, Collection $bidangsContactMap): ?array
    {
        if (empty($row->kegiatan) && empty($row->nama_instansi)) {
            return null;
        }

        $stageId  = $this->resolveStageId((int) $row->id, $pitchingRekaps, $stages);
        $status   = $this->resolveStatus($row);
        $nominal  = $this->resolveNominal($row);
        $output   = $this->resolveOutput($row->kegiatan, $outputs);

        // FIX #3: Gunakan resolveContactId yang punya fallback via bidangs
        $contactId = $this->resolveContactId($row, $contactsMap, $bidangsContactMap);

        $projectionStatus = match (true) {
            $row->closing  !== null => 'Closing',
            $row->deal     !== null => 'Deal',
            $row->optimis  !== null => 'Optimis',
            $row->moderate !== null => 'Moderate',
            $row->lost     !== null => 'Lost',
            default                 => 'Proyeksi',
        };

        $followUps = [];
        if ($row->tgl_fu || $row->hasil_fu) {
            $followUps[] = [
                'date'   => $row->tgl_fu,
                'result' => $row->hasil_fu,
            ];
        }

        $historyLogs = array_filter([
            'partnership_prediksi_id' => (string) $row->id,
            'id_ukw'                  => $row->id_ukw,
            'id_pmo'                  => $row->id_pmo,
            'id_provinsi'             => $row->id_provinsi,
            'id_kabupaten'            => $row->id_kabupaten,
            'id_instansi'             => $row->id_instansi,
            'id_bidang'               => $row->id_bidang,
            'jenis'                   => $row->jenis,
            'bulan'                   => $row->bulan,
            'bulan_awal'              => $row->bulan_awal,
            'bulan_akhir'             => $row->bulan_akhir,
            'sumber_informasi'        => $row->sumber_informasi,
            'profiling_market'        => $row->profiling_market,
            'kode_output_partnership' => $row->kegiatan,
            'nama_instansi'           => $row->nama_instansi,
            'monev'                   => array_filter([
                'proyeksi'  => $row->monev_proyeksi,
                'budget'    => $row->monev_budget,
                'authority' => $row->monev_authority,
                'needs'     => $row->monev_needs,
                'timeline'  => $row->monev_timeline,
                'kontrak'   => $row->monev_kontrak,
            ]) ?: null,
        ]);

        return [
            'title'                  => $output?->output_name ?? $row->nama_instansi ?? $row->kegiatan,
            'output_id'              => $output?->id,
            'stage_id'               => $stageId,
            'contact_id'             => $contactId,
            'nominal_value'          => $nominal,
            'status'                 => $status,
            'sumber_proyeksi'        => $row->rp_sumber_proyeksi ?? $row->sumber_informasi ?? $row->sumber,
            'detail_sumber_proyeksi' => $row->profiling_market,
            'pitching_date'          => $this->resolvePitchingDate((int) $row->id, $pitchingRekaps, $row),
            'communication_method'   => $row->rp_metode,
            'fiscal_year'            => 2026,
            'archived_at'            => $status === 'lost' ? ($row->tanggal_closing ?? now()) : null,
            'negative_reason'        => $row->alasan_closing ?? $row->rp_alasan_negative,
            'projection_month'       => $this->buildProjectionDate((string) $row->tahun, (string) ($row->bulan_pencairan ?? $row->bulan ?? ''),(string) ($row->rp_pekan ?? $row->pekan ?? '')),
            'projection_week'        => $row->rp_pekan ?? $row->pekan,
            'projection_status'      => $projectionStatus,
            'drive_link'             => $row->link_acc ?? $row->rp_link_dokumen ?? $row->link_branding,
            'manager_verified'       => (bool) $row->verifikasi_manager,
            'history_logs'           => json_encode($historyLogs),
            'follow_ups'             => json_encode($followUps),
        ];
    }

    private function resolveOutput(?string $partnershipCode, Collection $outputs): ?object
    {
        if (! $partnershipCode) {
            return null;
        }

        if ($outputs->has($partnershipCode)) {
            return $outputs->get($partnershipCode);
        }

        return $outputs->first(fn ($o) => str_starts_with($o->generated_code, $partnershipCode.'.'));
    }

    private function resolveStatus(object $row): string
    {
        return match (true) {
            $row->closing  !== null => 'won',
            $row->deal     !== null => 'won',
            $row->optimis  !== null => 'hot',
            $row->moderate !== null => 'warm',
            $row->lost     !== null => 'lost',
            default                 => 'Active',
        };
    }

    private function resolveStageId(int $prediksiId, Collection $pitchingRekaps, array $stages): int
    {
        $rekaps = $pitchingRekaps->get($prediksiId);

        if ($rekaps?->isNotEmpty()) {
            $done = $rekaps->first(
                fn ($r) => $r->rekap_terlaksana !== null && $r->rekap_terlaksana !== 'Reschedule'
            );
            if ($done) {
                return $stages['Terlaksana'];
            }

            $cancelled = $rekaps->first(fn ($r) => $r->rekap_tdk_terlaksana !== null);
            if ($cancelled) {
                return $stages['Tidak Terlaksana'];
            }
        }

        return $stages['Rencana'];
    }

    private function resolvePitchingDate(int $prediksiId, Collection $pitchingRekaps, object $row): ?string
    {
        $rekaps = $pitchingRekaps->get($prediksiId);
        if ($rekaps?->isNotEmpty()) {
            $done = $rekaps->first(
                fn ($r) => $r->rekap_terlaksana !== null && $r->rekap_terlaksana !== 'Reschedule'
            );
            if ($done?->rekap_tgl) {
                return $done->rekap_tgl;
            }
        }

        if ($row->rp_tanggal_pitching) {
            return $row->rp_tanggal_pitching;
        }

        return $row->tgl_bimtek;
    }

    private function buildProjectionDate(string $year, string $month, string $week): ?string
    {
        if ($month === '') return null;
        $y   = (int) ($year ?: 2026);
        $m   = (int) $month;
        $w   = max(1, (int) ($week ?: 1));
        $day = ($w - 1) * 7 + 1;
        $lastDay = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        $day = min($day, $lastDay);
        return sprintf('%04d-%02d-%02d 07:00:00', $y, $m, $day);
    }

    private function resolveNominal(object $row): float
    {
        $candidates = [
            $row->closing,
            $row->deal,
            $row->optimis,
            $row->moderate,
            $row->proyeksi,
            $row->rp_anggaran_proyeksi ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $cleaned = preg_replace('/[^\d]/', '', (string) $value);
            if ($cleaned !== '' && $cleaned !== '0') {
                return (float) $cleaned;
            }
        }

        return 0.0;
    }
}