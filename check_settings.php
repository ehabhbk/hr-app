<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$settings = DB::table('settings')->get(['key', 'value']);

echo "=== الإعدادات ===\n\n";
foreach($settings as $s) {
    echo "Key: {$s->key} | Value: {$s->value}\n";
}
