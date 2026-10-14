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
 * Two groups may share a module permission (Partnership is both an application module and a Data Master block).
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
        'finance' => [
            'section' => 'module',
            'label' => 'Finance',
            'description' => 'Intake, reconciliation, disbursements, and clearance archive.',
            'icon' => 'banknotes',
            'module' => 'access-finance',
            'resources' => [
                'finance-intake' => ['label' => 'Intake', 'actions' => ['view']],
                'finance-reconciliation' => ['label' => 'Reconciliation', 'actions' => ['view']],
                'finance-disbursements' => ['label' => 'Disbursements', 'actions' => ['view']],
                'finance-clearance-archive' => ['label' => 'Clearance Archive', 'actions' => ['view']],
            ],
        ],
        'partnership' => [
            'section' => 'module',
            'label' => 'Partnership',
            'description' => 'Sales pipeline, contacts, pitching, and partnership realisation.',
            'icon' => 'user-group',
            'module' => 'access-partnership',
            'resources' => [
                'partnership-dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
                'partnership-contacts' => ['label' => 'Contacts', 'actions' => ['view']],
                'partnership-pitching' => ['label' => 'Pitching', 'actions' => ['view']],
                'partnership-realisasi' => ['label' => 'Realisasi', 'actions' => ['view']],
                'partnership-pmo' => ['label' => 'PMO', 'actions' => ['view']],
                'partnership-data-coverage' => ['label' => 'Data Coverage', 'actions' => ['view']],
                'partnership-activity-log' => ['label' => 'Activity Log', 'actions' => ['view']],
            ],
        ],
        'investors' => [
            'section' => 'module',
            'label' => 'Investors / Funding',
            'description' => 'Investor and funding data.',
            'icon' => 'chart-bar',
            'module' => 'access-investors',
            'resources' => [
                'investors-dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
                'investors-monthly' => ['label' => 'Monthly Achievement', 'actions' => ['view']],
                'investors-conversion' => ['label' => 'Monthly Conversion', 'actions' => ['view']],
                'fo-funnel' => ['label' => 'Funnel', 'actions' => ['view', 'create', 'update', 'delete', 'restore']],
                'fo-contacts' => ['label' => 'Contacts', 'actions' => ['view', 'create', 'update', 'delete']],
                'fo-targets' => ['label' => 'Management FO (Target)', 'actions' => ['view', 'update']],
                'investors-activity-log' => ['label' => 'Activity Log', 'actions' => ['view']],
                'investor-documents' => ['label' => 'Master Document', 'actions' => ['view', 'create', 'update', 'delete', 'restore']],
            ],
        ],
        'technology' => [
            'section' => 'module',
            'label' => 'Technology',
            'description' => 'Internal tools for the Technology team, e.g. the Timeline Calculator.',
            'icon' => 'cpu-chip',
            'module' => 'access-technology',
            'resources' => [
                'tech-timeline-calc' => ['label' => 'Timeline Calculator', 'actions' => ['view']],
                'tech-sentry' => ['label' => 'Sentry', 'actions' => ['view']],
                'tech-documentation' => ['label' => 'API Documentation', 'actions' => ['view']],
                'tech-ai-monitor' => ['label' => 'AI Monitor (token usage & request log, no chat content)', 'actions' => ['view']],
            ],
        ],
        'ai-data' => [
            'section' => 'module',
            'label' => 'AI Center Data',
            'description' => 'Aggregated data the AI Center may read for the user (config/ai_datasets.php).',
            'icon' => 'sparkles',
            'module' => 'manage-ai-center',
            // Data access is never handed out in bulk by ResourcePermissions::grantLegacyAccess(): tick it per role.
            'legacy_grant' => false,
            'resources' => [
                'ai-data-ai-usage' => ['label' => 'AI Center Usage', 'actions' => ['view']],
                'ai-data-fo-achievement' => ['label' => 'Funding Officer Achievement (per person)', 'actions' => ['view']],
                'ai-data-employees' => ['label' => 'Master: Employees (name, NIS, department, position)', 'actions' => ['view']],
                'ai-data-departments' => ['label' => 'Master: Departments', 'actions' => ['view']],
                'ai-data-roles' => ['label' => 'Master: Roles (user counts)', 'actions' => ['view']],
                'ai-data-regions' => ['label' => 'Master: Regions', 'actions' => ['view']],
                'ai-data-opds' => ['label' => 'Master: OPD', 'actions' => ['view']],
                'ai-data-urusans' => ['label' => 'Master: Urusan', 'actions' => ['view']],
                'ai-data-products' => ['label' => 'Master: Products', 'actions' => ['view']],
                'ai-data-all-departments' => ['label' => 'All Departments (otherwise own department only)', 'actions' => ['view']],
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
        'master-pipeline' => [
            'section' => 'master',
            'label' => 'Pipeline',
            'description' => 'Regions, OPD, contact positions, and urusan.',
            'icon' => 'queue-list',
            'module' => 'access-partnership',
            'resources' => [
                'region' => ['label' => 'Region', 'actions' => ['view']],
                'opd' => ['label' => 'OPD', 'actions' => ['view']],
                'contact-positions' => ['label' => 'Contact Positions', 'actions' => ['view']],
                'urusan' => ['label' => 'Urusan', 'actions' => ['view']],
            ],
        ],
        'master-products' => [
            'section' => 'master',
            'label' => 'Products',
            'description' => 'Product master data.',
            'icon' => 'cube',
            'module' => 'access-products',
            'resources' => [
                'products' => ['label' => 'Products', 'actions' => ['view']],
            ],
        ],
    ],
];
