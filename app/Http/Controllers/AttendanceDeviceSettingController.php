<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDeviceLog;
use App\Models\AttendanceDeviceSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;

class AttendanceDeviceSettingController extends Controller
{
    /**
     * Return or create the singleton settings row.
     *
     * @return AttendanceDeviceSetting
     */
    private function singleton(): AttendanceDeviceSetting
    {
        return AttendanceDeviceSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'enabled' => false,
                'name' => 'Fingerprint Device',
                'host' => null,
                'port' => 4370,
                'driver' => 'zk',
                'timeout_ms' => 3000,
                'sync_interval_seconds' => 300,
            ]
        );
    }

    /**
     * Show current settings.
     */
   public function show()
{
    try {
        $setting = $this->singleton();

        // جلب كل الأجهزة المسجلة (إن وُجد موديل AttendanceDevice)
        $devices = [];
        try {
            $devices = \App\Models\AttendanceDevice::orderBy('id', 'desc')->get()->map(function ($d) {
                // تأكد من وجود host: إذا الواجهة أرسلت ip سابقاً قد يكون الحقل ip محفوظ في DB
                if (empty($d->host) && !empty($d->ip)) {
                    $d->host = $d->ip;
                }
                return $d;
            });
        } catch (\Throwable $inner) {
            // لو لم يكن جدول الأجهزة موجوداً أو حصل خطأ، سجّله لكن لا تكسر الاستجابة
            Log::warning('AttendanceDeviceSettingController@show: failed to load devices: '.$inner->getMessage());
            $devices = [];
        }

        return response()->json([
            'settings' => $setting,
            'devices' => $devices,
        ], 200);
    } catch (\Exception $e) {
        Log::error('AttendanceDeviceSettingController@show error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['message' => 'Server error'], 500);
    }
}

    /**
     * Add or create/update settings via POST (compatibility for front-end that sends POST).
     * This method mirrors update but accepts POST requests and returns the saved settings.
     */
   public function add(Request $request)
{
    try {
        $setting = $this->singleton();

        // دعم ip -> host إذا الواجهة ترسل ip
        if ($request->filled('ip') && !$request->filled('host')) {
            $request->merge(['host' => $request->input('ip')]);
        }

        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'name' => 'sometimes|string|max:255',
            'host' => 'required|string|max:255', // اجعلها مطلوبة
            'port' => 'sometimes|integer|min:1|max:65535',
            'driver' => 'sometimes|string|in:zk,soap,tcp,http',
            'timeout_ms' => 'sometimes|integer|min:100|max:60000',
            'sync_interval_seconds' => 'sometimes|integer|min:10|max:86400',
        ]);

        $setting->fill($data);
        $setting->save();

        return response()->json(['data' => $setting], 200);
    } catch (\Illuminate\Validation\ValidationException $ve) {
        return response()->json(['message' => 'Validation failed', 'errors' => $ve->errors()], 422);
    } catch (\Exception $e) {
        Log::error('AttendanceDeviceSettingController@add error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['message' => 'Server error'], 500);
    }
}

    /**
     * Update settings (PUT).
     */
    public function update(Request $request)
    {
        try {
            $setting = $this->singleton();

            $data = $request->validate([
                'enabled' => 'sometimes|boolean',
                'name' => 'sometimes|string|max:255',
                'host' => 'sometimes|nullable|string|max:255',
                'port' => 'sometimes|integer|min:1|max:65535',
                'driver' => 'sometimes|string|in:zk,soap,tcp,http',
                'timeout_ms' => 'sometimes|integer|min:100|max:60000',
                'sync_interval_seconds' => 'sometimes|integer|min:10|max:86400',
            ]);

            $setting->fill($data);
            $setting->save();

            return response()->json(['data' => $setting]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Validation failed', 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AttendanceDeviceSettingController@update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    /**
     * Test connection to a device using provided host/port or current settings.
     * Accepts optional host, port, timeout_ms in request body.
     */
    public function testConnection(Request $request)
    {
        try {
            $setting = $this->singleton();

            $data = $request->validate([
                'host' => 'sometimes|nullable|string|max:255',
                'port' => 'sometimes|integer|min:1|max:65535',
                'timeout_ms' => 'sometimes|integer|min:100|max:60000',
            ]);

            $host = array_key_exists('host', $data) ? $data['host'] : $setting->host;
            $port = array_key_exists('port', $data) ? $data['port'] : $setting->port;
            $timeoutMs = array_key_exists('timeout_ms', $data) ? $data['timeout_ms'] : $setting->timeout_ms;

            if (!$host) {
                return response()->json(['message' => 'host مطلوب لاختبار الاتصال'], 422);
            }

            $timeoutSeconds = max(0.1, $timeoutMs / 1000);
            $start = microtime(true);
            $errno = 0;
            $errstr = '';

            $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($fp) {
                fclose($fp);
                return response()->json([
                    'ok' => true,
                    'latency_ms' => $latencyMs,
                    'host' => $host,
                    'port' => $port,
                ]);
            }

            return response()->json([
                'ok' => false,
                'latency_ms' => $latencyMs,
                'host' => $host,
                'port' => $port,
                'error' => [
                    'code' => $errno,
                    'message' => $errstr ?: 'Connection failed',
                ],
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Validation failed', 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AttendanceDeviceSettingController@testConnection error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    /**
     * Sync logs from the configured device (singleton).
     * Accepts optional clear_device_logs boolean.
     */
    public function sync(Request $request)
    {
        try {
            if (!extension_loaded('sockets')) {
                return response()->json([
                    'message' => 'PHP sockets extension غير مفعّل. فعّل sockets من php.ini ثم أعد التشغيل.',
                ], 500);
            }

            $setting = $this->singleton();

            if (!$setting->enabled) {
                return response()->json(['message' => 'جهاز البصمة غير مفعّل (enabled=false)'], 422);
            }

            if (!$setting->host) {
                return response()->json(['message' => 'host غير مضبوط في إعدادات جهاز البصمة'], 422);
            }

            $request->validate([
                'clear_device_logs' => 'sometimes|boolean',
            ]);

            $zk = new ZKTeco($setting->host, (int) $setting->port);

            if (!$zk->connect()) {
                return response()->json([
                    'message' => 'فشل الاتصال بجهاز البصمة. تأكد من IP/Port وأن الجهاز على نفس الشبكة.',
                ], 422);
            }

            $zk->disableDevice();
            $logs = $zk->getAttendance();
            $zk->enableDevice();

            if ($request->boolean('clear_device_logs')) {
                $zk->clearAttendance();
            }

            $zk->disconnect();

            if (!is_array($logs)) {
                return response()->json(['message' => 'تعذر قراءة سجلات الحضور من الجهاز'], 422);
            }

            $rows = [];
            foreach ($logs as $log) {
                $deviceUserId = (string) ($log['id'] ?? '');
                $ts = $log['timestamp'] ?? null;
                if (!$deviceUserId || !$ts) {
                    continue;
                }

                $rows[] = [
                    'uid' => isset($log['uid']) ? (int) $log['uid'] : null,
                    'device_user_id' => $deviceUserId,
                    'state' => isset($log['state']) ? (int) $log['state'] : null,
                    'timestamp' => Carbon::parse($ts)->toDateTimeString(),
                    'raw' => json_encode($log, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($rows)) {
                AttendanceDeviceLog::query()->upsert(
                    $rows,
                    ['device_user_id', 'timestamp', 'state'],
                    ['uid', 'raw', 'updated_at']
                );
            }

            $setting->last_sync_at = now();
            $setting->save();

            return response()->json([
                'ok' => true,
                'fetched' => is_array($logs) ? count($logs) : 0,
                'stored' => count($rows),
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Validation failed', 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('AttendanceDeviceSettingController@sync error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Server error'], 500);
        }
    }
}