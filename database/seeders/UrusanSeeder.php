<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UrusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['code' => '01', 'urusan' => 'Bidang Pendidikan'],
            ['code' => '02', 'urusan' => 'Bidang Kesehatan'],
            ['code' => '03', 'urusan' => 'Bidang Pekerjaan Umum dan Penataan Ruang'],
            ['code' => '04', 'urusan' => 'Bidang Perumahan dan Kawasan Permukiman'],
            ['code' => '05', 'urusan' => 'Bidang Ketenteraman dan Ketertiban Umum serta Perlindungan Masyarakat'],
            ['code' => '06', 'urusan' => 'Bidang Sosial'],
            ['code' => '07', 'urusan' => 'Bidang Tenaga Kerja'],
            ['code' => '08', 'urusan' => 'Bidang Pemberdayaan Perempuan dan Perlindungan Anak'],
            ['code' => '09', 'urusan' => 'Bidang Pangan'],
            ['code' => '10', 'urusan' => 'Bidang Pertanahan'],
            ['code' => '11', 'urusan' => 'Bidang Lingkungan Hidup'],
            ['code' => '12', 'urusan' => 'Bidang Administrasi Kependudukan dan Pencatatan Sipil'],
            ['code' => '13', 'urusan' => 'Bidang Pemberdayaan Masyarakat dan Desa'],
            ['code' => '14', 'urusan' => 'Bidang Pengendalian Penduduk dan Keluarga Berencana'],
            ['code' => '15', 'urusan' => 'Bidang Perhubungan'],
            ['code' => '16', 'urusan' => 'Bidang Komunikasi dan Informatika'],
            ['code' => '17', 'urusan' => 'Bidang Koperasi, Usaha Kecil, dan Menengah'],
            ['code' => '18', 'urusan' => 'Bidang Penanaman Modal'],
            ['code' => '19', 'urusan' => 'Bidang Kepemudaan dan Olahraga'],
            ['code' => '20', 'urusan' => 'Bidang Statistik'],
            ['code' => '21', 'urusan' => 'Bidang Persandian'],
            ['code' => '22', 'urusan' => 'Bidang Kebudayaan'],
            ['code' => '23', 'urusan' => 'Bidang Perpustakaan'],
            ['code' => '24', 'urusan' => 'Bidang Kearsipan'],
            ['code' => '25', 'urusan' => 'Bidang Kelautan dan Perikanan'],
            ['code' => '26', 'urusan' => 'Bidang Pariwisata'],
            ['code' => '27', 'urusan' => 'Bidang Pertanian'],
            ['code' => '28', 'urusan' => 'Bidang Kehutanan'],
            ['code' => '29', 'urusan' => 'Bidang Energi dan Sumber Daya Mineral'],
            ['code' => '30', 'urusan' => 'Bidang Perdagangan'],
            ['code' => '31', 'urusan' => 'Bidang Perindustrian'],
            ['code' => '32', 'urusan' => 'Bidang Transmigrasi'],
            ['code' => '33', 'urusan' => 'Bidang Dewan'],
            ['code' => '34', 'urusan' => 'Bidang Kecamatan'],
            ['code' => '35', 'urusan' => 'Bidang Kepegawaian dan Sumber Daya Manusia'],
            ['code' => '36', 'urusan' => 'Bidang Kesatuan Bangsa dan Politik'],
            ['code' => '37', 'urusan' => 'Bidang Keuangan dan Aset'],
            ['code' => '38', 'urusan' => 'Bidang Organisasi dan Tata Laksana'],
            ['code' => '39', 'urusan' => 'Bidang Penanggulangan Bencana'],
            ['code' => '40', 'urusan' => 'Bidang Pendapatan'],
            ['code' => '41', 'urusan' => 'Bidang Penelitian dan Pengembangan'],
            ['code' => '42', 'urusan' => 'Bidang Pengawasan'],
            ['code' => '43', 'urusan' => 'Bidang Perbatasan'],
            ['code' => '44', 'urusan' => 'Bidang Perencanaan dan Pembangunan'],
            ['code' => '45', 'urusan' => 'Bidang Perkebunan'],
            ['code' => '46', 'urusan' => 'Bidang Peternakan'],
            ['code' => '47', 'urusan' => 'Bidang Sumber Daya Air'],
            ['code' => '48', 'urusan' => 'Bidang Pengadaan Barang Jasa'],
            ['code' => '49', 'urusan' => 'Bidang Syariat Islam'],
            ['code' => '50', 'urusan' => 'Bidang Hukum'],
            ['code' => '51', 'urusan' => 'Bidang Administrasi Pemerintahan'],
            ['code' => '52', 'urusan' => 'Bidang Perekonomian'],
            ['code' => '53', 'urusan' => 'Bidang Protokol Pemerintahan'],
            ['code' => '54', 'urusan' => 'Bidang Administrasi Pembangunan'],
            ['code' => '55', 'urusan' => 'Bidang Kesejahteraan Rakyat'],
            ['code' => '56', 'urusan' => 'Bidang Umum'],
            ['code' => '57', 'urusan' => 'Bidang Kerjasama Pemerintahan'],
            ['code' => '58', 'urusan' => 'Bidang Sumber Daya Alam'],
        ];

        $now = now();
        $insertData = array_map(function ($item) use ($now) {
            return array_merge($item, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $data);

        DB::table('urusans')->insert($insertData);
    }
}
