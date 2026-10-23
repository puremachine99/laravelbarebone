<?php

/**
 * Per-resource action permissions (the "sub-menu" checklist inside each module card on the role settings page).
 *
 * Every permission is named "{resource}.{action}". The module permission ("access-*") still decides whether a
 * user may enter the module at all; these decide which sub-menu they can open ("view") and what they can do
 * inside it. Only list an action if it is really enforced — the role matrix renders exactly what is here.
 * Sub-menus that only list "view" have page access enforced but no per-action rules yet.
 *
 * Groups render as one card each: `section` picks the heading ("module" = Application Modules, "master" = Data Master).
 *
 * @return array{actions: array<string, string>, groups: array<string, array{section: string, label: string, description: string, icon: string, module: string, legacy_grant?: bool, resources: array<string, array{label: string, actions: array<int, string>, legacy_write_permission?: string}>}>}
 */
return [
    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'export' => 'Export CSV',
        'restore' => 'Restore',
    ],

    'groups' => [
        'technology' => [
            'section' => 'module',
            'label' => 'Technology',
            'description' => 'Internal tools for the Technology team, e.g. Sentry.',
            'icon' => 'cpu-chip',
            'module' => 'access-technology',
            'resources' => [
                'tech-sentry' => ['label' => 'Sentry', 'actions' => ['view']],
            ],
        ],
        'master-internal' => [
            'section' => 'master',
            'label' => 'Internal',
            'description' => 'Departments, positions, employees, and role management.',
            'icon' => 'building-office',
            'module' => 'access-internal-org',
            'resources' => [
                'departments' => ['label' => 'Departments', 'actions' => ['view', 'create', 'update', 'delete'], 'legacy_write_permission' => 'modify-master-data'],
                'positions' => ['label' => 'Positions', 'actions' => ['view', 'create', 'update', 'delete']],
                'employees' => ['label' => 'Employees', 'actions' => ['view', 'create', 'update', 'delete', 'export', 'restore']],
            ],
        ],
    ],
];
