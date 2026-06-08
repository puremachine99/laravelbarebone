<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/jsonlawas/instansis.json');

        if (! File::exists($filePath)) {
            $this->command->warn('File instansis.json tidak ditemukan!');

            return;
        }

        $this->command->info('Membaca file instansis.json...');
        $rawContent = File::get($filePath);
        $rawContent = str_replace('\\\\"', '\\"', $rawContent);
        $rawContent = str_replace("\\'", "'", $rawContent);

        $data = json_decode($rawContent, true);

        if (! is_array($data)) {
            $this->command->error('Format instansis.json tidak valid!');

            return;
        }

        $this->command->info('Memuat data wilayah dan urusan ke memori...');
        $regencies = DB::table('regencies')->pluck('id', 'code')->toArray();
        $urusans = DB::table('urusans')->pluck('id', 'code')->toArray();

        $opdData = [];
        $opdUrusanPivot = [];
        $now = now();
        $opdCounter = 1;

        $this->command->info('Memproses data OPD...');

        foreach ($data as $item) {
            $oldKabupatenCode = $item['id_kabupaten'] ?? null;
            if (! $oldKabupatenCode) {
                continue;
            }

            $regencyId = $regencies[$oldKabupatenCode] ?? null;
            if (! $regencyId) {
                continue;
            }

            $opdId = $opdCounter++;

            $opdData[] = [
                'id' => $opdId,
                'regency_id' => $regencyId,
                'name' => $item['instansi'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rawUrusans = $item['id_urusan'] ?? '';
            $urusanCodes = [];

            if (is_string($rawUrusans)) {
                $cleaned = str_replace(['[', ']', '"', '\\', ' '], '', $rawUrusans);
                if ($cleaned !== '') {
                    $urusanCodes = explode(',', $cleaned);
                }
            } elseif (is_array($rawUrusans)) {
                $urusanCodes = $rawUrusans;
            }

            $urusanCodes = array_unique($urusanCodes);

            foreach ($urusanCodes as $code) {
                $urusanId = $urusans[$code] ?? null;
                if ($urusanId) {
                    $opdUrusanPivot[] = [
                        'opd_id' => $opdId,
                        'urusan_id' => $urusanId,
                    ];
                }
            }
        }

        $totalOpds = count($opdData);
        $this->command->info("Memulai Bulk Insert {$totalOpds} data OPD...");

        DB::transaction(function () use ($opdData, $opdUrusanPivot) {
            DB::connection()->disableQueryLog();

            foreach (array_chunk($opdData, 1000) as $chunk) {
                DB::table('opds')->insert($chunk);
            }

            foreach (array_chunk($opdUrusanPivot, 1000) as $chunk) {
                DB::table('opd_urusan')->insert($chunk);
            }
        });

        $this->command->info('Seeding OPD berhasil diselesaikan!');
    }
}
