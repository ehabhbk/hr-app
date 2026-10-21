<?php

namespace App\Console\Commands;

use App\Http\Controllers\AttendanceRecordController;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mithun\PhpZkteco\Libs\ZKTeco;

class ListenAttendance extends Command
{
    protected $signature = 'attendance:listen
        {--device= : الاستماع لجهاز محدد فقط (بدون هذا الخيار يستمع لجميع الأجهزة المفعّلة بالتناوب)}
        {--window=30 : مدة الاستماع لكل جهاز بالثواني داخل كل دورة (0 = استماع لا نهائي لجهاز واحد)}';

    protected $description = 'الاستماع اللحظي لبصمات الحضور من أجهزة البصمة — تظهر البصمة في سجل الحضور فور تسجيلها';

    public function handle()
    {
        $window = max(0, (int) $this->option('window'));
        $singleDevice = (bool) $this->option('device');

        $this->info('بدء المستمع اللحظي لسجل الحضور...');

        while (true) {
            $deviceId = $this->option('device');
            $devices = $deviceId
                ? AttendanceDevice::where('id', $deviceId)->where('enabled', true)->whereNotNull('host')->get()
                : AttendanceDevice::where('enabled', true)->whereNotNull('host')->get();

            if ($devices->isEmpty()) {
                $this->warn('لا توجد أجهزة مفعّلة — إعادة المحاولة بعد 30 ثانية...');
                sleep(30);

                continue;
            }

            // جهاز واحد وwindow=0 → استماع لا نهائي بلا دوران (لا نصل للدورة التالية أبداً)
            $cycleWindow = ($singleDevice && $window === 0) ? 0 : ($window ?: 30);

            foreach ($devices as $device) {
                $this->listenTo($device, $cycleWindow);
            }

            // لا نوم إضافي بين الدورات — النوم يحدث داخل getRealTimeLogs عبر usleep
        }
    }

    private function listenTo(AttendanceDevice $device, int $window)
    {
        $protocol = $device->driver === 'tcp' ? 'tcp' : 'udp';

        try {
            $zk = new ZKTeco($device->host, (int) ($device->port ?: 4370), protocol: $protocol, timeout: 10);
        } catch (\Exception $e) {
            Log::error("attendance:listen connect error ({$device->name}): ".$e->getMessage());
            $this->warn("فشل الاتصال بجهاز {$device->name}: ".$e->getMessage());
            sleep(5);

            return;
        }

        try {
            if (! $zk->connect()) {
                $this->warn("تعذر الاتصال بجهاز {$device->name} ({$device->host}) — إعادة المحاولة لاحقاً");
                sleep(5);

                return;
            }
        } catch (\Exception $e) {
            Log::error("attendance:listen connect error ({$device->name}): ".$e->getMessage());
            sleep(5);

            return;
        }

        $this->info("جاري الاستماع لجهاز {$device->name} ({$device->host}) — نافذة {$window}s");

        // ملاحظة: لا نستخدم disableDevice أثناء الاستماع حتى يبقى الموظفون قادرون على البصمة
        try {
            $zk->getRealTimeLogs(function (array $event) use ($device) {
                $this->handlePunch($device, $event);
            }, $window);
        } catch (\Exception $e) {
            Log::error("attendance:listen error ({$device->name}): ".$e->getMessage());
            $this->warn("انقطع الاستماع من {$device->name}: ".$e->getMessage());
        }

        try {
            $zk->disconnect();
        } catch (\Exception $e) {
            // تجاهل خطأ قطع الاتصال
        }

        $device->last_sync_at = now();
        $device->save();
    }

    private function handlePunch(AttendanceDevice $device, array $event)
    {
        $userId = trim((string) ($event['user_id'] ?? ''));
        $ts = $event['record_time'] ?? null;
        $state = isset($event['state']) ? (int) $event['state'] : null;

        if ($userId === '' || ! $ts) {
            return;
        }

        $ts = Carbon::parse($ts)->toDateTimeString();

        // منع التكرار — نفس البصمة قد تسحبها المزامنة الدورية attendance:auto
        $exists = AttendanceDeviceLog::where('device_id', $device->id)
            ->where('device_user_id', $userId)
            ->where('timestamp', $ts)
            ->where('state', $state)
            ->exists();
        if ($exists) {
            return;
        }

        try {
            AttendanceDeviceLog::create([
                'device_id' => $device->id,
                'device_user_id' => $userId,
                'state' => $state,
                'timestamp' => $ts,
                'raw' => json_encode($event, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // سباق مع المزامنة الدورية على المفتاح الفريد — تجاهل بصمت
            return;
        }

        $this->info("بصمة فورية: {$userId} — {$ts} — {$device->name}");

        $this->processToday($device);
    }

    /**
     * معالجة سجلات اليوم لهذا الجهاز فوراً لتظهر في سجل الحضور مباشرة.
     * القفل داخل processFromDeviceLogs نفسها يمنع التزامن مع attendance:auto.
     */
    private function processToday(AttendanceDevice $device)
    {
        try {
            app(AttendanceRecordController::class)->processFromDeviceLogs(new Request([
                'from_date' => now()->format('Y-m-d'),
                'to_date' => now()->format('Y-m-d'),
                'device_id' => $device->id,
            ]));
        } catch (\Throwable $e) {
            Log::error('attendance:listen process error: '.$e->getMessage());
        }
    }
}
