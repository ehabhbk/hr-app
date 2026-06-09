<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$org = \App\Models\Setting::where('key', 'organization')->first();
if ($org && $org->value) {
    echo "Organization Logo: " . ($org->value['logo'] ?? 'None') . "\n";
    
    if (!empty($org->value['logo'])) {
        $logoPath = public_path('storage/' . $org->value['logo']);
        echo "Logo Path: $logoPath\n";
        echo "Exists: " . (file_exists($logoPath) ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "No organization settings found\n";
}
