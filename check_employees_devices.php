<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$employees = DB::table('employees')
    ->select('id', 'name', 'device_user_id', 'attendance_device_id')
    ->get();

echo "=== الموظفين وأجهزة البصمة ===\n\n";
foreach($employees as $e) {
    $deviceName = $e->attendance_device_id ? 
        DB::table('attendance_devices')->where('id', $e->attendance_device_id)->value('name') : '-';
    echo "{$e->name} | device_user_id: {$e->device_user_id} | device_id: {$e->attendance_device_id} | Device: {$deviceName}\n";
}
