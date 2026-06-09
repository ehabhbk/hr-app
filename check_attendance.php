<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emp = \App\Models\Employee::with(['attendanceRecords'])->first();

if ($emp) {
    echo "Employee: " . $emp->name . "\n\n";
    
    foreach ($emp->attendanceRecords as $record) {
        echo "Record #" . $record->id . ":\n";
        echo "  Date: " . $record->date . "\n";
        echo "  Has delay: " . ($record->has_delay ? 'Yes' : 'No') . "\n";
        echo "  Check in type: " . ($record->check_in_type ?? 'N/A') . "\n";
        echo "  Check out type: " . ($record->check_out_type ?? 'N/A') . "\n";
        echo "  Deduction applied: " . ($record->deduction_applied ? 'Yes' : 'No') . "\n";
        echo "  Total deduction: " . ($record->total_deduction ?? 0) . "\n";
        echo "  Delay excused: " . ($record->delay_excused ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
}
