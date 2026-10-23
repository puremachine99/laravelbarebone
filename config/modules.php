<?php

/**
 * Canonical registry of business modules that a Department can be mapped to.
 *
 * Add an entry here (plus a matching group in config/resource_permissions.php) when you build a new module.
 *
 * @return array<string, array{label: string, description: string, permission: string, icon: string, admin_only: bool}>
 */
return [
    'list' => [
        'internal-org' => [
            'label' => 'Internal Org',
            'description' => 'Departments, positions, employees, serta user & role management.',
            'permission' => 'access-internal-org',
            'icon' => 'building-office',
            'admin_only' => true,
        ],
        'technology' => [
            'label' => 'Technology',
            'description' => 'Tools internal tim Technology, mis. Sentry.',
            'permission' => 'access-technology',
            'icon' => 'cpu-chip',
            'admin_only' => false,
        ],
    ],
];
