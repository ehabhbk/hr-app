<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET sql_mode = "NO_ENGINE_SUBSTITUTION"');
echo "Done\n";