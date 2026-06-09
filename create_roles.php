<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = [
    'hr_manager' => [
        'display_name' => 'مدير الموارد البشرية',
        'permissions' => [
            'menu.dashboard', 'menu.employees', 'menu.attendance', 'menu.settings',
            'employees.view', 'employees.create', 'employees.edit',
            'attendance.view', 'attendance.manage',
            'leaves.view', 'leaves.manage', 'leaves.approve',
            'warnings.manage', 'warnings.view',
            'settings.view', 'settings.manage',
            'reports.view'
        ]
    ],
    'finance_manager' => [
        'display_name' => 'المدير المالي',
        'permissions' => [
            'menu.dashboard', 'menu.reports', 'menu.settings', 'menu.bank_export',
            'salaries.view', 'salaries.manage', 'salaries.export', 'salaries.process',
            'allowances.manage', 'allowances.view',
            'settings.view', 'settings.manage',
            'reports.view', 'reports.export',
            'tab.reports_salary', 'tab.reports_income_tax'
        ]
    ],
    'department_supervisor' => [
        'display_name' => 'مشرف القسم',
        'permissions' => [
            'menu.dashboard', 'menu.employees',
            'employees.view',
            'leaves.view', 'leaves.approve',
            'allowances.view',
            'reports.view'
        ]
    ]
];

foreach ($roles as $name => $data) {
    $role = \App\Models\Role::updateOrCreate(
        ['name' => $name],
        [
            'display_name' => $data['display_name'],
            'permissions' => $data['permissions']
        ]
    );
    echo "Role '{$name}' (ID: {$role->id}) - Permissions count: " . count($data['permissions']) . "\n";
}

// Set admin with all permissions
$admin = \App\Models\Role::updateOrCreate(
    ['name' => 'admin'],
    [
        'display_name' => 'مدير النظام',
        'permissions' => ['*']
    ]
);
echo "Role 'admin' (ID: {$admin->id}) - Full permissions\n";

echo "\nDone! All roles created.\n";
