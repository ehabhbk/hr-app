<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emp = \App\Models\Employee::with('advances')->first();

if ($emp) {
    echo "Employee: " . $emp->name . "\n";
    echo "Advances count: " . $emp->advances->count() . "\n\n";
    
    foreach ($emp->advances as $a) {
        echo "  - Type: " . $a->type . "\n";
        echo "    Amount: " . $a->amount . "\n";
        echo "    Remaining: " . $a->remaining_amount . "\n";
        echo "    Status: " . $a->status . "\n";
        echo "    Monthly Installment: " . ($a->monthly_installment ?? 'N/A') . "\n";
        echo "\n";
    }
} else {
    echo "No employees found\n";
}
