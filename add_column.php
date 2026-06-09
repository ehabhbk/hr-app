<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('ALTER TABLE users ADD full_name VARCHAR(255) NULL AFTER username');
    echo "Added full_name column successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}