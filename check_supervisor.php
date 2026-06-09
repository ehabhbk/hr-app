<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Role;

// Get users with department_supervisor role
$supervisorRole = Role::where('name', 'department_supervisor')->first();
echo "Supervisor Role ID: " . $supervisorRole->id . "\n";

$supervisors = User::where('role_id', $supervisorRole->id)->get();
echo "Supervisors count: " . $supervisors->count() . "\n";

foreach ($supervisors as $user) {
    echo "User: " . $user->username . " - role_id: " . $user->role_id . " - department_id: " . $user->department_id . "\n";
}

// Check employees in department
if ($supervisors->first() && $supervisors->first()->department_id) {
    $deptId = $supervisors->first()->department_id;
    $employees = App\Models\Employee::where('department_id', $deptId)->count();
    echo "Employees in department $deptId: " . $employees . "\n";
}