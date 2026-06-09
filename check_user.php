<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$user = User::where('username', 'super')->first();
echo "User: " . $user->username . "\n";
echo "Role ID: " . $user->role_id . "\n";

$user->load('role');
echo "Role: " . ($user->role ? $user->role->name : 'null') . "\n";
echo "Role permissions: ";
print_r($user->role ? $user->role->permissions : []);

$isSupervisor = $user->role && $user->role->name === 'department_supervisor' && $user->department_id;
echo "\nIs supervisor: " . ($isSupervisor ? 'YES' : 'NO') . "\n";
echo "Department ID: " . $user->department_id . "\n";