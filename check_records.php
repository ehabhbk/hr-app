<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AttendanceRecord;

$records = AttendanceRecord::with('employee')->orderBy('date', 'desc')->take(5)->get();

echo "=== سجلات الحضور المحسوبة ===\n\n";
foreach($records as $r) {
    echo "الموظف: {$r->employee->name}\n";
    echo "التاريخ: {$r->date}\n";
    echo "الدخول: {$r->check_in_time}\n";
    echo "الخروج: {$r->check_out_time}\n";
    echo "نوع الدخول: {$r->check_in_type}\n";
    echo "تأخير: {$r->check_in_delay_minutes} دقيقة\n";
    echo "---\n";
}
