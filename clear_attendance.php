<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AttendanceRecord;
use App\Models\AttendanceDeviceLog;
use App\Models\Employee;
use Carbon\Carbon;

// Delete all attendance records
AttendanceRecord::truncate();

echo "تم حذف جميع سجلات الحضور\n";
echo "الآن قم بالمزامنة مرة أخرى من الصفحة\n";
