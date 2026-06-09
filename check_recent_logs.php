<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$logs = DB::table('attendance_device_logs')
    ->orderBy('timestamp', 'desc')
    ->take(10)
    ->get(['id', 'device_user_id', 'timestamp', 'state']);

echo "=== آخر 10 سجلات من الجهاز ===\n\n";
foreach($logs as $log) {
    $stateLabel = $log->state == 1 ? 'دخول' : ($log->state == 2 ? 'خروج' : 'غير معروف');
    echo "{$log->timestamp} | {$stateLabel} (state: {$log->state})\n";
}
