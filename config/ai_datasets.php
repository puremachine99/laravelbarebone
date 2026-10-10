<?php

use App\Services\AiData\Datasets\AiUsageDataset;
use App\Services\AiData\Datasets\FoAchievementDataset;

/**
 * AI Center data catalog (see report/ai-center-data-query-api.md).
 *
 * This is the only list of data the AI may read. Anything not listed here cannot be requested. Each dataset exposes
 * dimensions it may be grouped by and metrics the dataset class computes itself (the AI never picks a column or an
 * aggregate function). Metrics ending in `_pct` are ratios: null when they cannot be computed.
 *
 * Allowed: basic work information such as a person's name, NIS (employee ID), department, position and KPI results.
 * Never add sensitive personal data: NIK (the 16-digit KTP number in `employees.nik`), NPWP, employment status,
 * marital status, birth date, gender, phone, email, address, salary or bank account.
 *
 * @return array{min_group_size: int, max_rows: int, max_period_months: int, all_departments_permission: string, datasets: array<string, array{label: string, description: string, permission: string, source: class-string, dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>, min_group_size?: int}>}
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
    ],
];
