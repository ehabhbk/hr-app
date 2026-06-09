<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emp = \App\Models\Employee::with(['deductions', 'attendanceRecords'])->first();

if ($emp) {
    echo "Employee: " . $emp->name . "\n";
    echo "Deductions count: " . $emp->deductions->count() . "\n";
    
    foreach ($emp->deductions as $d) {
        echo "  - Deduction: " . $d->type . " = " . $d->amount . "\n";
    }
    
    echo "Attendance Records count: " . $emp->attendanceRecords->count() . "\n";
    
    $lateCount = $emp->attendanceRecords->where('has_delay', true)->count();
    echo "Late arrivals: " . $lateCount . "\n";
} else {
    echo "No employees found!\n";
}

// Check deductions table
$deductionsTable = \Illuminate\Support\Facades\Schema::hasTable('deductions');
echo "\nDeductions table exists: " . ($deductionsTable ? 'Yes' : 'No') . "\n";

if ($deductionsTable) {
    $totalDeductions = \App\Models\Deduction::count();
    echo "Total deductions in DB: " . $totalDeductions . "\n";
}
