<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('sync:master {--only=* : Limit to specific tables: urusans,provinces,regencies,opds,products}')]
#[Description('Sync master data (urusans, provinces, regencies, opds, products) from the external Partnership App database')]
class SyncPartnershipMasterData extends Command
{
    private string $connection = 'mysql_partnership';

    public function handle(): int
    {
        if (! config("database.connections.{$this->connection}")) {
            $this->error("Database connection '{$this->connection}' is not configured in config/database.php");

            return Command::FAILURE;
        }

        try {
            DB::connection($this->connection)->getPdo();
        } catch (\Exception $e) {
            $this->error('Failed to connect to external database: '.$e->getMessage());

            return Command::FAILURE;
        }

        $only = $this->option('only');

        $steps = [
            'urusans' => fn () => $this->syncUrusans(),
            'provinces' => fn () => $this->syncProvinces(),
            'regencies' => fn () => $this->syncRegencies(),
            'opds' => fn () => $this->syncOpds(),
            'products' => fn () => $this->syncProducts(),
        ];

        foreach ($steps as $key => $step) {
            if (! empty($only) && ! in_array($key, $only)) {
                continue;
            }

            $step();
        }

        $this->newLine();
        $this->info('Master data sync completed.');

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────
    // SYNC: urusans → urusans
    // ─────────────────────────────────────────────────────

    private function syncUrusans(): void
    {
        if (! Schema::connection($this->connection)->hasTable('urusans')) {
            $this->warn('[urusans] Table not found in external DB, skipping.');

            return;
        }

        $this->info('[urusans] Syncing...');

        $rows = DB::connection($this->connection)
            ->table('urusans')
            ->select('kode', 'urusan')
            ->get();

        $upserted = 0;

        foreach ($rows as $row) {
            DB::table('pmo_urusans')->updateOrInsert(
                ['code' => $row->kode],
                ['urusan' => $row->urusan, 'updated_at' => now()]
            );
            $upserted++;
        }

        $this->info("[urusans] Done — {$upserted} rows upserted.");
    }

    // ─────────────────────────────────────────────────────
    // SYNC: provinsis → provinces
    // ─────────────────────────────────────────────────────

    private function syncProvinces(): void
    {
        if (! Schema::connection($this->connection)->hasTable('provinsis')) {
            $this->warn('[provinces] Table not found in external DB, skipping.');

            return;
        }

        $this->info('[provinces] Syncing...');

        $rows = DB::connection($this->connection)
            ->table('provinsis')
            ->select('kode', 'provinsi')
            ->get();

        $upserted = 0;

        foreach ($rows as $row) {
            DB::table('provinces')->updateOrInsert(
                ['code' => $row->kode],
                ['name' => $row->provinsi, 'updated_at' => now()]
            );
            $upserted++;
        }

        $this->info("[provinces] Done — {$upserted} rows upserted.");
    }

    // ─────────────────────────────────────────────────────
    // SYNC: kabupatens → regencies
    // ─────────────────────────────────────────────────────

    private function syncRegencies(): void
    {
        if (! Schema::connection($this->connection)->hasTable('kabupatens')) {
            $this->warn('[regencies] Table not found in external DB, skipping.');

            return;
        }

        $this->info('[regencies] Syncing...');

        // Build province code → local province_id map
        $provinceMap = DB::table('provinces')
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->toArray();

        $rows = DB::connection($this->connection)
            ->table('kabupatens')
            ->select('kode', 'kode_provinsi', 'kabupaten', 'ukw')
            ->get();

        $upserted = 0;
        $skipped = 0;

        // Pre-fetch valid region IDs to avoid per-row query
        $validRegionIds = DB::table('regions')->pluck('id')->flip()->toArray();

        foreach ($rows as $row) {
            $provinceId = $provinceMap[$row->kode_provinsi] ?? null;

            if (! $provinceId) {
                $skipped++;

                continue;
            }

            $type = str_starts_with(strtolower($row->kabupaten), 'kota') ? 'Kota' : 'Kabupaten';
            $regionId = ($row->ukw && isset($validRegionIds[(int) $row->ukw])) ? (int) $row->ukw : null;

            DB::table('regencies')->updateOrInsert(
                ['code' => $row->kode],
                [
                    'province_id' => $provinceId,
                    'region_id' => $regionId,
                    'name' => $row->kabupaten,
                    'type' => $type,
                    'updated_at' => now(),
                ]
            );

            $upserted++;
        }

        $this->info("[regencies] Done — {$upserted} upserted, {$skipped} skipped (province not found).");
    }

    // ─────────────────────────────────────────────────────
    // SYNC: instansis → opds
    // ─────────────────────────────────────────────────────

    private function syncOpds(): void
    {
        if (! Schema::connection($this->connection)->hasTable('instansis')) {
            $this->warn('[opds] Table not found in external DB, skipping.');

            return;
        }

        $this->info('[opds] Syncing...');

        // Build kabupaten kode → local regency_id map
        $regencyMap = DB::table('regencies')
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->toArray();

        // Pre-load existing opds as "regency_id:name" set to avoid duplicate inserts
        $existingOpds = DB::table('pmo_opds')
            ->select('regency_id', 'name')
            ->get()
            ->mapWithKeys(fn ($o) => ["{$o->regency_id}:{$o->name}" => true])
            ->toArray();

        // Fix PostgreSQL sequence if it's out of sync with actual max id
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('pmo_opds_id_seq', COALESCE((SELECT MAX(id) FROM pmo_opds), 0) + 1, false)");
        }

        $total = DB::connection($this->connection)->table('instansis')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $skipped = 0;
        $batch = [];

        DB::connection($this->connection)
            ->table('instansis')
            ->select('id_kabupaten', 'instansi')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($regencyMap, &$existingOpds, $bar, &$inserted, &$skipped, &$batch) {
                foreach ($rows as $row) {
                    $regencyId = $regencyMap[$row->id_kabupaten] ?? null;
                    $name = trim($row->instansi ?? '');

                    if (! $regencyId || $name === '') {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    $key = "{$regencyId}:{$name}";

                    if (isset($existingOpds[$key])) {
                        $bar->advance();

                        continue;
                    }

                    $existingOpds[$key] = true;
                    $batch[] = [
                        'regency_id' => $regencyId,
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $inserted++;
                    $bar->advance();

                    if (count($batch) >= 200) {
                        DB::table('pmo_opds')->insert($batch);
                        $batch = [];
                    }
                }
            });

        if (! empty($batch)) {
            DB::table('pmo_opds')->insert($batch);
        }

        $bar->finish();
        $this->newLine();
        $this->info("[opds] Done — {$inserted} inserted, {$skipped} skipped (regency not found / empty name).");
    }

    // ─────────────────────────────────────────────────────
    // SYNC: outputs → divisions + service_centers + product_categories + outputs
    // ─────────────────────────────────────────────────────

    private function syncProducts(): void
    {
        if (! Schema::connection($this->connection)->hasTable('outputs')) {
            $this->warn('[products] Table "outputs" not found in external DB, skipping.');

            return;
        }

        $this->info('[products] Syncing divisions, service_centers, product_categories, outputs...');

        $rows = DB::connection($this->connection)
            ->table('outputs')
            ->select('id_divisi', 'divisi', 'id_pl', 'pl', 'id_output', 'output', 'kategori', 'pelaksana', 'status_output')
            ->whereNotNull('id_output')
            ->where('id_output', '!=', '')
            ->orderBy('id_output')
            ->get();

        // ── 1. Divisions ──────────────────────────────────
        $this->line('  → divisions');
        $rows->groupBy('id_divisi')->each(function ($group, $divCode) {
            if (! $divCode) {
                return;
            }

            DB::table('pmo_divisions')->updateOrInsert(
                ['code' => $divCode],
                ['name' => $group->first()->divisi ?? $divCode, 'updated_at' => now()]
            );
        });

        $divisionMap = DB::table('pmo_divisions')->pluck('id', 'code')->toArray();

        // ── 2. Service Centers ────────────────────────────
        $this->line('  → service_centers');
        $rows->groupBy('id_pl')->each(function ($group, $plCode) use ($divisionMap) {
            if (! $plCode) {
                return;
            }

            $first = $group->first();
            $divisionId = $divisionMap[$first->id_divisi] ?? null;

            if (! $divisionId) {
                return;
            }

            // code = last segment of id_pl (e.g. "A.2" → "2")
            $code = (string) (str_contains($plCode, '.') ? substr($plCode, strrpos($plCode, '.') + 1) : $plCode);

            DB::table('pmo_service_centers')->updateOrInsert(
                ['full_code' => $plCode],
                [
                    'division_id' => $divisionId,
                    'code' => $code,
                    'name' => $first->pl ?? $plCode,
                    'updated_at' => now(),
                ]
            );
        });

        $serviceCenterMap = DB::table('pmo_service_centers')->pluck('id', 'full_code')->toArray();

        // ── 3. Product Categories ─────────────────────────
        $this->line('  → product_categories');
        $rows->pluck('kategori')->filter()->unique()->each(function ($kategori) {
            DB::table('pmo_product_categories')->updateOrInsert(
                ['code' => $kategori],
                ['name' => $kategori, 'updated_at' => now()]
            );
        });

        // ── 4. Outputs ────────────────────────────────────
        $this->line('  → outputs');
        $total = $rows->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $upserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $divisionId = $divisionMap[$row->id_divisi] ?? null;
            $serviceCenterId = $serviceCenterMap[$row->id_pl] ?? null;

            if (! $divisionId || ! $serviceCenterId || ! $row->kategori) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // sequence = last numeric segment of id_output (e.g. "A.2.3" → 3)
            $sequence = (int) (str_contains($row->id_output, '.') ? substr($row->id_output, strrpos($row->id_output, '.') + 1) : $row->id_output);

            DB::table('pmo_outputs')->updateOrInsert(
                ['generated_code' => $row->id_output],
                [
                    'division_id' => $divisionId,
                    'service_center_id' => $serviceCenterId,
                    'category_code' => $row->kategori,
                    'output_name' => $row->output,
                    'pelaksana' => $row->pelaksana ?: null,
                    'status' => strtolower($row->status_output ?? '') !== 'nonaktif',
                    'sequence' => $sequence,
                    'updated_at' => now(),
                ]
            );

            $upserted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("[products] Done — {$upserted} outputs upserted, {$skipped} skipped.");
    }
}
