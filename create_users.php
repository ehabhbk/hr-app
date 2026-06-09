<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = [
    [
        'username' => 'admin',
        'name' => 'مدير النظام',
        'email' => 'admin@jawda.com',
        'password' => 'admin123',
        'role' => 'admin',
        'department_id' => null
    ],
    [
        'username' => 'hr_manager',
        'name' => 'أحمد محمد علي',
        'email' => 'hr@jawda.com',
        'password' => 'hr123456',
        'role' => 'hr_manager',
        'department_id' => null
    ],
    [
        'username' => 'finance_manager',
        'name' => 'خالد عبدالله',
        'email' => 'finance@jawda.com',
        'password' => 'finance123456',
        'role' => 'finance_manager',
        'department_id' => null
    ],
    [
        'username' => 'supervisor_1',
        'name' => 'عمر يوسف',
        'email' => 'supervisor@jawda.com',
        'password' => 'super123456',
        'role' => 'department_supervisor',
        'department_id' => 1 // الموارد البشرية
    ]
];

foreach ($users as $userData) {
    $role = \App\Models\Role::where('name', $userData['role'])->first();
    $deptId = $userData['department_id'] ?? null;
    
    $user = \App\Models\User::updateOrCreate(
        ['username' => $userData['username']],
        [
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => bcrypt($userData['password']),
            'role_id' => $role->id,
            'department_id' => $deptId
        ]
    );
    
    $deptName = $deptId ? \App\Models\Department::find($deptId)->name : '---';
    echo "User '{$userData['username']}' (ID: {$user->id}) - Role: {$userData['role']} - Dept: {$deptName}\n";
}

echo "\nDone! All users updated.\n";
echo "\n=== Login Credentials ===\n";
echo "Admin: admin / admin123\n";
echo "HR Manager: hr_manager / hr123456\n";
echo "Finance Manager: finance_manager / finance123456\n";
echo "Department Supervisor: supervisor_1 / super123456 (قسم الموارد البشرية)\n";
