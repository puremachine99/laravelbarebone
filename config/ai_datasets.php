<?php

use App\Services\AiData\Datasets\AiUsageDataset;
use App\Services\AiData\Datasets\DepartmentsDataset;
use App\Services\AiData\Datasets\EmployeesDataset;
use App\Services\AiData\Datasets\FoAchievementDataset;
use App\Services\AiData\Datasets\OpdsDataset;
use App\Services\AiData\Datasets\ProductsDataset;
use App\Services\AiData\Datasets\RegionsDataset;
use App\Services\AiData\Datasets\RolesDataset;
use App\Services\AiData\Datasets\UrusansDataset;

/**
 * AI Center data catalog (see report/ai-center-data-query-api.md).
 *
 * This is the only list of data the AI may read, and the "data master" the AI service reads (GET /api/ai/datasets) to
 * route a question: `skill` and `department` say which topic/department owns the data. Anything not listed here
 * cannot be requested. Each dataset exposes
 * dimensions it may be grouped by and metrics the dataset class computes itself (the AI never picks a column or an
 * aggregate function). Metrics ending in `_pct` are ratios: null when they cannot be computed.
 *
 * Allowed: basic work information such as a person's name, NIS (employee ID), department, position and KPI results.
 * Never add sensitive personal data: NIK (the 16-digit KTP number in `employees.nik`), NPWP, employment status,
 * marital status, birth date, gender, phone, email, address, salary or bank account.
 *
 * @return array{min_group_size: int, max_rows: int, max_period_months: int, all_departments_permission: string, datasets: array<string, array{skill: string, department: string|null, label: string, description: string, permission: string, source: class-string, time_based?: bool, department_scoped?: bool, dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>, min_group_size?: int}>}
 *
 * `time_based` (default true): false for master data; periods are then rejected and the result has no period.
 * `department_scoped` (default true): false for shared reference data that every allowed user sees in full.
 */
return [
    /*
     * Rows built from fewer people than this are hidden, otherwise an aggregate of a tiny department is effectively
     * one person's data. A dataset that is per person by design (and only shows allowed work data) sets its own
     * `min_group_size` to 1.
     */
    'min_group_size' => 5,

    'max_rows' => 200,

    /*
     * Longest explicit from/to range. `all_time` requests are not limited by this.
     */
    'max_period_months' => 24,

    /*
     * Without this permission a user only gets rows of their own department.
     */
    'all_departments_permission' => 'ai-data-all-departments.view',

    'datasets' => [
        'ai_usage' => [
            'skill' => 'ai_center',
            'department' => null,
            'label' => 'Pemakaian AI Center',
            'description' => 'Jumlah pertanyaan yang dikirim ke AI Center dan jumlah pengguna yang bertanya.',
            'permission' => 'ai-data-ai-usage.view',
            'source' => AiUsageDataset::class,
            'dimensions' => [
                'month' => 'Bulan (YYYY-MM)',
                'department' => 'Nama departemen',
            ],
            'metrics' => [
                'questions' => 'Jumlah pertanyaan ke AI Center',
                'active_users' => 'Jumlah pengguna yang bertanya',
            ],
            'filters' => ['department'],
        ],

        'fo_achievement' => [
            'skill' => 'funding',
            'department' => 'Departemen Finance',
            'label' => 'Capaian Funding Officer (modul Funding)',
            'description' => 'Realisasi funnel per Funding Officer dan capaian nilai closing terhadap target tahunan yang tersimpan. '
                .'Target tahunan dibagi rata per bulan; tahun tanpa target tersimpan tidak menambah target.',
            'permission' => 'ai-data-fo-achievement.view',
            'source' => FoAchievementDataset::class,
            'min_group_size' => 1,
            'dimensions' => [
                'employee' => 'Nama Funding Officer',
                'department' => 'Nama departemen',
                'month' => 'Bulan (YYYY-MM)',
            ],
            'metrics' => [
                'leads' => 'Jumlah leads baru',
                'ql' => 'Jumlah qualified lead',
                'pitching' => 'Jumlah funnel yang di-pitching',
                'won' => 'Jumlah funnel won',
                'closing' => 'Jumlah closing (deal)',
                'nilai_closing' => 'Total nominal investasi closing (Rp)',
                'target_nilai_closing' => 'Target nilai closing untuk periode (Rp)',
                'achievement_pct' => 'Capaian nilai closing terhadap target (%)',
            ],
            'filters' => ['department'],
        ],

        /*
         * Master data (read only, not time based). Filters are case-insensitive partial matches.
         */
        'employees' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data karyawan aktif',
            'description' => 'Karyawan aktif: nama, NIS (ID karyawan), departemen, posisi (struktural/fungsional/teknis), dan region. '
                .'Untuk daftar nama per departemen/posisi atau jumlah karyawan. Karyawan yang sudah tidak aktif tidak termasuk.',
            'permission' => 'ai-data-employees.view',
            'source' => EmployeesDataset::class,
            'time_based' => false,
            'min_group_size' => 1,
            'dimensions' => [
                'employee' => 'Nama karyawan',
                'nis' => 'NIS (ID karyawan)',
                'department' => 'Nama departemen',
                'position_struktural' => 'Posisi struktural',
                'position_fungsional' => 'Posisi fungsional',
                'position_teknis' => 'Posisi teknis',
                'region' => 'Region',
            ],
            'metrics' => ['employees' => 'Jumlah karyawan aktif'],
            'filters' => ['department', 'name', 'position', 'region'],
        ],

        'departments' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data departemen',
            'description' => 'Daftar departemen beserta kode dan jumlah karyawan aktif.',
            'permission' => 'ai-data-departments.view',
            'source' => DepartmentsDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => ['department' => 'Nama departemen', 'code' => 'Kode departemen'],
            'metrics' => ['employees' => 'Jumlah karyawan aktif'],
            'filters' => ['name'],
        ],

        'roles' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data role aplikasi',
            'description' => 'Role (hak akses) di aplikasi Nakama dan jumlah pengguna yang memilikinya, bisa per departemen. Tanpa nama pengguna.',
            'permission' => 'ai-data-roles.view',
            'source' => RolesDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => ['role' => 'Nama role', 'department' => 'Departemen pengguna'],
            'metrics' => ['users' => 'Jumlah pengguna dengan role ini'],
            'filters' => ['role', 'department'],
        ],

        'regions' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data region',
            'description' => 'Region wilayah kerja beserta jumlah provinsi, kabupaten/kota, dan OPD yang dipetakan ke region tersebut.',
            'permission' => 'ai-data-regions.view',
            'source' => RegionsDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => ['region' => 'Nama region', 'code' => 'Kode region'],
            'metrics' => [
                'provinces' => 'Jumlah provinsi',
                'regencies' => 'Jumlah kabupaten/kota',
                'opds' => 'Jumlah OPD',
            ],
            'filters' => ['name'],
        ],

        'opds' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data OPD / instansi',
            'description' => 'OPD dan instansi pemerintah (jenis: OPD, Kementrian, Unit Kesehatan) dengan kabupaten/kota, provinsi, region, dan bidang urusan. '
                .'Gunakan filter agar daftar tidak terlalu panjang.',
            'permission' => 'ai-data-opds.view',
            'source' => OpdsDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => [
                'opd' => 'Nama OPD/instansi',
                'jenis' => 'Jenis instansi',
                'regency' => 'Kabupaten/kota',
                'province' => 'Provinsi',
                'region' => 'Region',
                'urusan' => 'Bidang urusan',
            ],
            'metrics' => ['opds' => 'Jumlah OPD/instansi'],
            'filters' => ['name', 'jenis', 'province', 'regency', 'region', 'urusan'],
        ],

        'urusans' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data bidang urusan',
            'description' => 'Bidang urusan pemerintahan beserta kodenya dan jumlah OPD yang menangani urusan tersebut.',
            'permission' => 'ai-data-urusans.view',
            'source' => UrusansDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => ['urusan' => 'Nama bidang urusan', 'code' => 'Kode urusan'],
            'metrics' => ['opds' => 'Jumlah OPD dengan urusan ini'],
            'filters' => ['name'],
        ],

        'products' => [
            'skill' => 'master_data',
            'department' => null,
            'label' => 'Data produk',
            'description' => 'Master produk (output): nama, kode, kategori, service center, divisi, pelaksana, dan status aktif.',
            'permission' => 'ai-data-products.view',
            'source' => ProductsDataset::class,
            'time_based' => false,
            'department_scoped' => false,
            'min_group_size' => 1,
            'dimensions' => [
                'product' => 'Nama produk',
                'code' => 'Kode produk',
                'category' => 'Kategori produk',
                'service_center' => 'Service center',
                'division' => 'Divisi',
                'pelaksana' => 'Pelaksana',
                'status' => 'Status (Aktif/Nonaktif)',
            ],
            'metrics' => ['products' => 'Jumlah produk'],
            'filters' => ['name', 'category', 'service_center', 'division', 'pelaksana'],
        ],
    ],
];
