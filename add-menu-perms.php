<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Add menu permissions to all roles
$menuPerms = [
    'menu.dashboard', 'menu.employees', 'menu.departments', 
    'menu.attendance', 'menu.devices', 'menu.salaries', 
    'menu.loans', 'menu.leaves', 'menu.reports', 'menu.settings',
    'menu.notifications', 'menu.bank_export'
];

$roles = App\Models\Role::all();

foreach ($roles as $role) {
    $currentPerms = $role->permissions;
    if (is_string($currentPerms)) {
        $currentPerms = json_decode($currentPerms, true) ?: [];
    }
    
    // Add menu permissions if not present
    foreach ($menuPerms as $mp) {
        if (!in_array($mp, $currentPerms)) {
            $currentPerms[] = $mp;
        }
    }
    
    $role->permissions = json_encode($currentPerms);
    $role->save();
    
    echo "Updated role: " . $role->name . " with " . count($currentPerms) . " permissions\n";
}
