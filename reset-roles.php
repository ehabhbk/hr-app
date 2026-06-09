<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Reset all roles except admin
$roles = App\Models\Role::where('name', '!=', 'admin')->get();

foreach ($roles as $role) {
    $role->permissions = json_encode(['*']);
    $role->save();
    echo "Reset role: " . $role->name . "\n";
}

echo "Done!\n";
