<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$logs = DB::table('attendance_device_logs')
    ->where('device_user_id', '1')
    ->orderBy('timestamp', 'desc')
    ->take(10)
    ->get(['id', 'device_user_id', 'timestamp', 'state']);

echo "=== سجلات حضور الجهاز للموظف رقم 1 ===\n\n";
foreach($logs as $log) {
    $stateLabel = $log->state == 1 ? 'دخول' : ($log->state == 2 ? 'خروج' : 'غير معروف');
    echo "ID: {$log->id} | State: {$log->state} ({$stateLabel}) | Time: {$log->timestamp}\n";
}
