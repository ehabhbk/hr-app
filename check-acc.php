<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountant = App\Models\Role::where('name', 'accountant')->first();
$perms = json_decode($accountant->permissions, true) ?: [];
echo "Has salaries.view: " . (in_array('salaries.view', $perms) ? 'YES' : 'NO') . "\n";
echo "Has salaries.view in permissions_array: " . (in_array('salaries.view', $accountant->permissions_array) ? 'YES' : 'NO') . "\n";
