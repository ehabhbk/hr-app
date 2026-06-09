<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::where('username', 'hr_test')->first();
$role = App\Models\Role::find($user->role_id);

echo "User: " . $user->username . "\n";
echo "Role ID: " . $user->role_id . "\n";
echo "Role name: " . $role->name . "\n";
echo "Permissions (type): " . gettype($role->getAttribute('permissions')) . "\n";
echo "Permissions (raw): ";
print_r($role->getAttribute('permissions'));
echo "Permissions (casted): ";
print_r($role->permissions);