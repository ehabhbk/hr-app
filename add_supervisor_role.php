<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$role = App\Models\Role::firstOrCreate(
    ['name' => 'department_supervisor'],
    [
        'name_ar' => 'مشرف قسم',
        'description' => 'مشرف قسم معين',
        'color' => '#0891b2',
        'permissions' => ['menu.dashboard', 'menu.employees', 'menu.attendance', 'employees.view', 'attendance.view', 'leaves.view', 'leaves.approve', 'warnings.view', 'warnings.create']
    ]
);

echo "Role created: " . $role->name . "\n";