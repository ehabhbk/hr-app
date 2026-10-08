<?php

namespace App\Console\Commands;

use App\Http\Controllers\AttendanceRecordController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoSyncAttendance extends Command
{
    protected $signature = 'attendance:auto';

    protected $description = 'مزامنة أجهزة البصمة ثم معالجة السجلات تلقائياً (حضور وانصراف)';

    public function handle()
    {
        // 1) سحب البصمات الجديدة من كل الأجهزة المفعّلة
        $this->call('attendance:sync');

        // 2) معالجة البصمات إلى سجلات حضور وانصراف لتظهر في السجل تلقائياً
        try {
            $controller = app(AttendanceRecordController::class);
            $response = $controller->processFromDeviceLogs(new Request([
                'from_date' => now()->startOfMonth()->format('Y-m-d'),
                'to_date' => now()->format('Y-m-d'),
            ]));
            $data = $response->getData(true);
            $this->info('Processed: ' . ($data['processed'] ?? 0)
                . ' | absences: ' . ($data['absences_marked'] ?? 0)
                . ' | unmatched: ' . count($data['unmatched'] ?? []));
        } catch (\Exception $e) {
            Log::error('attendance:auto process error: ' . $e->getMessage());
            $this->error('فشل المعالجة التلقائية: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }
}
