<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = \App\Models\Setting::where('key', 'advances')->first();

if ($setting) {
    echo "Advance Settings:\n";
    echo json_encode($setting->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "No advance settings found\n";
}
