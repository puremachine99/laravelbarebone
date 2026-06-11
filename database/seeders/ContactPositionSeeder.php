<?php

namespace Database\Seeders;

use App\Models\ContactPosition;
use Illuminate\Database\Seeder;

class ContactPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Utama (Eselon I.a)',
                'name' => 'Kepala LPNK setingkat Menteri (Kepala BKN, BPKP, BKPM, LAN, ANRI, BPS, BMKG)',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Utama (Eselon I.a)',
                'name' => 'Sekretaris Jenderal Lembaga Tinggi Negara (MPR, DPR, DPD, MA, MK, BPK, KY)',
                'tingkat' => 'Lembaga Tinggi Negara',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Utama (Eselon I.a)',
                'name' => 'Jaksa Agung Muda / Kepala Staf Kepresidenan',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Sekretaris Jenderal Kementerian',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Direktur Jenderal',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Inspektur Jenderal',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Deputi (Kemenko / LPNK)',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Staf Ahli Menteri',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Kepala Badan (di Kementerian)',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Sekretaris Utama (Sestama LPNK)',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Madya (Eselon I.a/I.b)',
                'name' => 'Sekretaris Daerah Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Direktur',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Kepala Biro',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Kepala Pusat',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Inspektur',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Sekretaris Ditjen/Badan/Itjen/Deputi',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Kepala Kantor Wilayah (Kanwil)',
                'tingkat' => 'Pusat/Vertikal',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Asisten Sekretaris Daerah Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Kepala Dinas Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Kepala Badan Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Sekretaris DPRD Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Staf Ahli Gubernur',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Inspektur Provinsi',
                'tingkat' => 'Provinsi',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Pimpinan Tinggi (JPT)',
                'eselon' => 'JPT Pratama (Eselon II.a/II.b)',
                'name' => 'Sekretaris Daerah Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Bagian',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Bidang',
                'tingkat' => 'Pusat/Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Subdirektorat',
                'tingkat' => 'Pusat',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Kantor (UPT Eselon III)',
                'tingkat' => 'Pusat/Vertikal',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Asisten Sekda Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Dinas Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Kepala Badan Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Sekretaris DPRD Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Staf Ahli Bupati/Walikota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Inspektur Kabupaten/Kota',
                'tingkat' => 'Kab/Kota',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Sekretaris Dinas/Badan',
                'tingkat' => 'Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Administrator (Eselon III.a/III.b)',
                'name' => 'Camat',
                'tingkat' => 'Kecamatan',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Kepala Subbagian',
                'tingkat' => 'Pusat/Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Kepala Seksi',
                'tingkat' => 'Pusat/Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Kepala Subbidang',
                'tingkat' => 'Pusat/Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Sekretaris Kecamatan (Sekcam)',
                'tingkat' => 'Kecamatan',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Lurah',
                'tingkat' => 'Kelurahan',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Kepala Seksi Kecamatan',
                'tingkat' => 'Kecamatan',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pengawas (Eselon IV.a/IV.b)',
                'name' => 'Kepala UPT (Eselon IV)',
                'tingkat' => 'Daerah',
            ],
            [
                'kelompok_jabatan' => 'Jabatan Administrasi (JA)',
                'eselon' => 'Pelaksana (eks Eselon V)',
                'name' => 'Kepala Subseksi / Kepala Urusan / Sekretaris Lurah (sebagian besar dialihkan ke JF)',
                'tingkat' => 'Daerah',
            ],
        ];

        foreach ($positions as $pos) {
            ContactPosition::updateOrCreate(
                ['name' => $pos['name']],
                [
                    'kelompok_jabatan' => $pos['kelompok_jabatan'],
                    'eselon' => $pos['eselon'],
                    'tingkat' => $pos['tingkat'],
                ]
            );
        }
    }
}
