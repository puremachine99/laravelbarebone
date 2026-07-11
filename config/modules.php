<?php

/**
 * Canonical registry of business modules that a Department can be mapped to.
 *
 * @return array<string, array{label: string, description: string, permission: string, icon: string, admin_only: bool}>
 */
return [
    'list' => [
        'finance' => [
            'label' => 'Finance',
            'description' => 'Intake, rekonsiliasi, disbursement, dan clearance archive.',
            'permission' => 'access-finance',
            'icon' => 'banknotes',
            'admin_only' => false,
        ],
        'partnership' => [
            'label' => 'Partnership',
            'description' => 'Sales pipeline, kontak, pitching, dan realisasi partnership.',
            'permission' => 'access-partnership',
            'icon' => 'user-group',
            'admin_only' => false,
        ],
        'products' => [
            'label' => 'Products',
            'description' => 'Master data produk.',
            'permission' => 'access-products',
            'icon' => 'cube',
            'admin_only' => false,
        ],
        'investors' => [
            'label' => 'Investors / Funding',
            'description' => 'Data investor dan funding.',
            'permission' => 'access-investors',
            'icon' => 'chart-bar',
            'admin_only' => false,
        ],
        'internal-org' => [
            'label' => 'Internal Org',
            'description' => 'Departments, positions, employees, serta user & role management.',
            'permission' => 'access-internal-org',
            'icon' => 'building-office',
            'admin_only' => true,
        ],
    ],
];
