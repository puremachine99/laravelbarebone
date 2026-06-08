<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('seeders/jsonlawas/products.json');

        if (! File::exists($filePath)) {
            $this->command->warn('File products.json tidak ditemukan!');

            return;
        }

        $this->command->info('Membaca file products.json...');
        $data = json_decode(File::get($filePath), true);

        if (! is_array($data)) {
            $this->command->error('Format products.json tidak valid!');

            return;
        }

        $this->command->info('Mengidentifikasi divisi, pusat layanan, dan kategori...');
        $divisions = [];
        $serviceCenters = [];
        $categories = [
            'A' => 'Penyusunan Dokumen / Smart Consulting',
            'B' => 'Bimbingan Teknis / Smart Offline Education',
            'C' => 'Kelas Online / Smart Online Education',
            'F' => 'Narasumber',
        ];

        foreach ($data as $item) {
            $divCode = $item['id_divisi'] ?? null;
            $divName = $item['divisi'] ?? null;
            if ($divCode && $divName) {
                $divisions[$divCode] = $divName;
            }

            $plFullCode = $item['id_pl'] ?? null;
            $plName = $item['pl'] ?? null;
            if ($plFullCode && $plName && $divCode) {
                // e.g. "1.1" -> code is "1", full_code is "1.1"
                $parts = explode('.', $plFullCode);
                $plCode = $parts[1] ?? '1';

                $serviceCenters[$plFullCode] = [
                    'division_code' => $divCode,
                    'code' => $plCode,
                    'name' => $plName,
                ];
            }
        }

        // Insert Divisions
        $this->command->info('Menyisipkan data divisi...');
        foreach ($divisions as $code => $name) {
            DB::table('divisions')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $divisionMap = DB::table('divisions')->pluck('id', 'code')->toArray();

        // Insert Service Centers
        $this->command->info('Menyisipkan data pusat layanan...');
        foreach ($serviceCenters as $fullCode => $info) {
            $divId = $divisionMap[$info['division_code']] ?? null;
            if ($divId) {
                DB::table('service_centers')->insertOrIgnore([
                    'division_id' => $divId,
                    'code' => $info['code'],
                    'full_code' => $fullCode,
                    'name' => $info['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $serviceCenterMap = DB::table('service_centers')->pluck('id', 'full_code')->toArray();

        // Insert Categories
        $this->command->info('Menyisipkan data kategori produk...');
        foreach ($categories as $code => $name) {
            DB::table('product_categories')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insert Outputs (Products)
        $this->command->info('Memproses dan menyisipkan data output...');
        $outputsData = [];
        $now = now();

        foreach ($data as $item) {
            $divId = $divisionMap[$item['id_divisi'] ?? ''] ?? null;
            $scId = $serviceCenterMap[$item['id_pl'] ?? ''] ?? null;

            if ($divId && $scId) {
                // Extract sequence from generated code, e.g. "1.1.A.2" -> 2
                $codeParts = explode('.', $item['id_output'] ?? '');
                $sequence = (int) end($codeParts);

                $outputsData[] = [
                    'division_id' => $divId,
                    'service_center_id' => $scId,
                    'category_code' => $item['kategori'] ?? 'A',
                    'output_name' => $item['output'] ?? '',
                    'pelaksana' => $item['pelaksana'] ?? null,
                    'status' => ($item['status_output'] ?? '1') === '1',
                    'sequence' => $sequence ?: 1,
                    'generated_code' => $item['id_output'] ?? '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($outputsData) {
            DB::connection()->disableQueryLog();
            foreach (array_chunk($outputsData, 500) as $chunk) {
                DB::table('outputs')->insert($chunk);
            }
        });

        $this->command->info('Seeding produk/output berhasil diselesaikan!');
    }
}
