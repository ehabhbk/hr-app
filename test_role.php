<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$role = App\Models\Role::firstOrCreate(
    ['name' => 'hr_manager'],
    [
        'name_ar' => 'مدير الموارد البشرية',
        'description' => 'إدارة الموارد البشرية',
        'color' => '#7c3aed',
        'permissions' => ['menu.dashboard', 'menu.employees', 'menu.departments', 'menu.attendance', 'menu.reports']
    ]
);

echo "Role: ";
print_r($role->toArray());
echo "\nPermissions: ";
print_r($role->permissions);
echo "\n";

// Create test user
$user = App\Models\User::firstOrCreate(
    ['username' => 'hr_test'],
    [
        'email' => 'hr@test.com',
        'password' => Hash::make('123456'),
        'role_id' => $role->id,
    ]
);
echo "Test user created: " . $user->username . " with role_id: " . $user->role_id . "\n";