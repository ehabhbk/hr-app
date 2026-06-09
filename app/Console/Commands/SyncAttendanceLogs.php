<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\Helper\Attendance;
use Jmrashed\Zkteco\Lib\ZKTeco;

class SyncAttendanceLogs extends Command
{
    protected $signature = 'attendance:sync {--device= : Specific device ID to sync}';

    protected $description = 'مزامنة سجلات الحضور من أجهزة البصمة';

    public function handle()
    {
        $deviceId = $this->option('device');

        if ($deviceId) {
            $devices = AttendanceDevice::where('id', $deviceId)->where('enabled', true)->get();
        } else {
            $devices = AttendanceDevice::where('enabled', true)->get();
        }

        if ($devices->isEmpty()) {
            $this->info('لا توجد أجهزة.enabledمفعّلة');

            return 0;
        }

        $this->info('بدء مزامنة '.$devices->count().' جهاز...');

        foreach ($devices as $device) {
            $this->syncDevice($device);
        }

        $this->info('اكتملت المزامنة');

        return 0;
    }

    private function syncDevice($device)
    {
        if (! $device->host) {
            $this->warn("الجهاز {$device->name} بدون host - يتم تخطيه");

            return;
        }

        try {
            $zk = new ZKTeco($device->host, (int) $device->port);

            if (! $zk->connect()) {
                $this->warn("فشل الاتصال بجهاز {$device->name}");

                return;
            }

            $zk->disableDevice();
            $logs = Attendance::get($zk);
            $zk->enableDevice();
            $zk->disconnect();

            if (! is_array($logs) || empty($logs)) {
                $this->info("لا توجد سجلات جديدة من جهاز {$device->name}");

                return;
            }

            $rows = [];
            foreach ($logs as $log) {
                $deviceUserId = (string) ($log['id'] ?? '');
                $ts = $log['timestamp'] ?? null;
                if (! $deviceUserId || ! $ts) {
                    continue;
                }

                $rows[] = [
                    'uid' => isset($log['uid']) ? (int) $log['uid'] : null,
                    'device_user_id' => $deviceUserId,
                    'device_id' => $device->id,
                    'state' => isset($log['state']) ? (int) $log['state'] : null,
                    'timestamp' => Carbon::parse($ts)->toDateTimeString(),
                    'raw' => json_encode($log, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($rows)) {
                AttendanceDeviceLog::query()->upsert(
                    $rows,
                    ['device_user_id', 'timestamp', 'state'],
                    ['uid', 'raw', 'updated_at']
                );

                $this->info('تم مزامنة '.count($rows)." سجل من {$device->name}");
            }

            $device->last_sync_at = now();
            $device->save();

        } catch (\Exception $e) {
            Log::error("Sync error for device {$device->name}: ".$e->getMessage());
            $this->error("خطأ في مزامنة {$device->name}: ".$e->getMessage());
        }
    }
}
