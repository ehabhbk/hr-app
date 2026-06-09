<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceLog;
use App\Models\Employee;
use App\Models\Fingerprint;
use App\Models\Face;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\ZKTecoService;
use Jmrashed\Zkteco\Lib\ZKTeco;

class AttendanceDeviceController extends Controller
{
    private function connect($device)
    {
        $zk = new ZKTeco($device->host, (int)($device->port ?? 4370));
        if (!$zk->connect()) {
            return null;
        }
        return $zk;
    }

    public function index()
    {
        return response()->json([
            'data' => AttendanceDevice::orderBy('id', 'desc')->get()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => AttendanceDevice::findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'name' => 'sometimes|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'sometimes|integer|min:1|max:65535',
            'driver' => 'sometimes|string|in:zk,soap,tcp,http',
            'serial_number' => 'nullable|string|max:255',
            'supports_face' => 'sometimes|boolean',
            'supports_fingerprint' => 'sometimes|boolean',
        ]);

        return response()->json([
            'data' => AttendanceDevice::create($data)
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'host' => 'sometimes|string|max:255',
            'port' => 'sometimes|integer|min:1|max:65535',
            'driver' => 'sometimes|string|in:zk,soap,tcp,http',
            'enabled' => 'sometimes|boolean',
            'serial_number' => 'nullable|string|max:255',
            'supports_face' => 'sometimes|boolean',
            'supports_fingerprint' => 'sometimes|boolean',
        ]);

        $device->update($data);
        return response()->json(['data' => $device]);
    }

    public function destroy($id)
    {
        AttendanceDevice::findOrFail($id)->delete();
        return response()->json(['message' => 'Device deleted']);
    }

    public function testConnection(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);
        $host = $request->input('host', $device->host);
        $port = $request->input('port', $device->port ?? 4370);

        try {
            $zk = new ZKTeco($host, (int)$port);

            $start = microtime(true);
            $connected = $zk->connect();
            $latency = (int)((microtime(true) - $start) * 1000);

            if ($connected) {
                $serial = null;
                $deviceName = null;
                $version = null;
                $faceSupported = false;
                try { $serial = $zk->serialNumber(); } catch (\Exception $e) {}
                try { $deviceName = $zk->deviceName(); } catch (\Exception $e) {}
                try { $version = $zk->version(); } catch (\Exception $e) {}
                try { $zk->faceFunctionOn(); $faceSupported = true; } catch (\Exception $e) {}

                $zk->disconnect();

                return response()->json([
                    'ok' => true,
                    'latency_ms' => $latency,
                    'serial_number' => $serial,
                    'device_name' => $deviceName,
                    'version' => $version,
                    'supports_face' => $faceSupported,
                    'message' => 'Connection successful'
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Connection failed'
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sync($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        if (!$device->enabled) {
            return response()->json(['message' => 'Device disabled'], 422);
        }

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->disableDevice();
        $logs = $zk->getAttendance();
        $zk->enableDevice();
        $zk->disconnect();

        if (!is_array($logs)) {
            return response()->json(['ok' => true, 'fetched' => 0]);
        }

        $inserted = 0;
        foreach ($logs as $log) {
            $userId = $log['id'] ?? null;
            $ts = $log['timestamp'] ?? null;
            if (!$userId || !$ts) continue;

            $time = Carbon::parse($ts)->toDateTimeString();

            $exists = AttendanceDeviceLog::where('device_id', $device->id)
                ->where('device_user_id', $userId)
                ->where('timestamp', $time)
                ->exists();

            if ($exists) continue;

            AttendanceDeviceLog::create([
                'device_id' => $device->id,
                'device_user_id' => $userId,
                'uid' => $log['uid'] ?? null,
                'state' => $log['state'] ?? null,
                'timestamp' => $time,
                'raw' => json_encode($log),
            ]);
            $inserted++;
        }

        return response()->json([
            'ok' => true,
            'fetched' => count($logs),
            'stored' => $inserted
        ]);
    }

    public function syncAll()
    {
        $devices = AttendanceDevice::where('enabled', true)->get();
        $results = [];

        foreach ($devices as $device) {
            try {
                $zk = $this->connect($device);
                if (!$zk) {
                    $results[] = ['device' => $device->name, 'status' => 'connection_failed'];
                    continue;
                }

                $zk->disableDevice();
                $logs = $zk->getAttendance();
                $zk->enableDevice();
                $zk->disconnect();

                $inserted = 0;
                if (is_array($logs)) {
                    foreach ($logs as $log) {
                        $userId = $log['id'] ?? null;
                        $ts = $log['timestamp'] ?? null;
                        if (!$userId || !$ts) continue;

                        $time = Carbon::parse($ts)->toDateTimeString();
                        $exists = AttendanceDeviceLog::where('device_id', $device->id)
                            ->where('device_user_id', $userId)
                            ->where('timestamp', $time)
                            ->exists();

                        if ($exists) continue;

                        AttendanceDeviceLog::create([
                            'device_id' => $device->id,
                            'device_user_id' => $userId,
                            'uid' => $log['uid'] ?? null,
                            'state' => $log['state'] ?? null,
                            'timestamp' => $time,
                            'raw' => json_encode($log),
                        ]);
                        $inserted++;
                    }
                }

                $device->last_sync_at = now();
                $device->save();

                $results[] = ['device' => $device->name, 'status' => 'ok', 'inserted' => $inserted];
            } catch (\Exception $e) {
                $results[] = ['device' => $device->name, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function registerUser(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'required|string',
            'name' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->setUser(
            (int)$data['user_id'],
            $data['user_id'],
            mb_strcut($data['name'] ?? $data['user_id'], 0, 24),
            $data['password'] ?? ''
        );

        $zk->disconnect();

        return response()->json([
            'success' => true,
            'message' => 'User created on device'
        ]);
    }

    public function removeUser(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'required'
        ]);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->removeUser($data['user_id']);
        $zk->disconnect();

        return response()->json(['success' => true]);
    }

    public function setTime($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->setTime(now()->toDateTimeString());
        $zk->disconnect();

        return response()->json(['ok' => true]);
    }

    public function enableDevice($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->enableDevice();
        $zk->disconnect();

        return response()->json(['ok' => true]);
    }

    public function disableDevice($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->disableDevice();
        $zk->disconnect();

        return response()->json(['ok' => true]);
    }

    public function getDeviceInfo($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $info = [];
        try { $info['serial_number'] = $zk->serialNumber(); } catch (\Exception $e) {}
        try { $info['device_name'] = $zk->deviceName(); } catch (\Exception $e) {}
        try { $info['version'] = $zk->version(); } catch (\Exception $e) {}
        try { $info['os_version'] = $zk->osVersion(); } catch (\Exception $e) {}
        try { $info['platform'] = $zk->platform(); } catch (\Exception $e) {}
        try { $info['fm_version'] = $zk->fmVersion(); } catch (\Exception $e) {}
        try { $info['work_code'] = $zk->workCode(); } catch (\Exception $e) {}
        try { $info['ssr'] = $zk->ssr(); } catch (\Exception $e) {}
        try { $info['pin_width'] = $zk->pinWidth(); } catch (\Exception $e) {}
        try { $zk->faceFunctionOn(); $info['supports_face'] = true; } catch (\Exception $e) { $info['supports_face'] = false; }
        try { $info['device_time'] = $zk->getTime(); } catch (\Exception $e) {}

        $zk->disconnect();

        return response()->json(['data' => $info]);
    }

    public function getDeviceUsers($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $users = $zk->getUser();
        $zk->disconnect();

        return response()->json(['data' => $users]);
    }

    public function downloadFingerprints($id, $uid = null)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $result = [];

        if ($uid) {
            $raw = $zk->getFingerprint((int)$uid);
            if (!empty($raw)) {
                foreach ($raw as $fingerId => $template) {
                    $result[$fingerId] = base64_encode($template);
                }
            }
        } else {
            $users = $zk->getUser();
            foreach ($users as $user) {
                $raw = $zk->getFingerprint($user['uid']);
                if (!empty($raw)) {
                    foreach ($raw as $fingerId => $template) {
                        $result[$user['userid']][$fingerId] = base64_encode($template);
                    }
                }
            }
        }

        $zk->disconnect();

        return response()->json(['data' => $result]);
    }

    public function uploadFingerprints(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'required|string',
            'fingerprints' => 'required|array',
            'fingerprints.*.finger_id' => 'required|integer|min:0|max:9',
            'fingerprints.*.template' => 'required|string',
        ]);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $userId = $data['user_id'];
        $fingerprintData = [];
        foreach ($data['fingerprints'] as $fp) {
            $fingerprintData[$fp['finger_id']] = base64_decode($fp['template']);
        }

        $count = $zk->setFingerprint((int)$userId, $fingerprintData);
        $zk->disconnect();

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "تم رفع $count بصمات للجهاز"
        ]);
    }

    /**
     * رفع قالب بصمة موجود مسبقاً إلى الجهاز (للمزامنة)
     * POST /attendance-device/{id}/register-fingerprint
     */
    public function registerFingerprint(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'finger_id' => 'required|integer|min:0|max:9',
            'template' => 'required|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $deviceUserId = $employee->device_user_id;
        if (!$deviceUserId) {
            return response()->json(['message' => 'الموظف ليس لديه رقم بصمة'], 422);
        }

        $templateData = base64_decode($data['template']);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->disableDevice();
        $success = $zk->enrollFingerprint((int)$deviceUserId, $data['finger_id'], $templateData);
        $zk->enableDevice();
        $zk->disconnect();

        if ($success === false) {
            return response()->json(['message' => 'فشل تسجيل البصمة على الجهاز'], 500);
        }

        $fingerprint = Fingerprint::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_device_id' => $device->id,
                'finger_id' => $data['finger_id'],
            ],
            [
                'template' => $data['template'],
                'finger_position' => $request->input('finger_position', 'right'),
                'finger' => $request->input('finger', 'thumb'),
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $fingerprint,
            'message' => 'تم تسجيل بصمة الإصبع بنجاح'
        ]);
    }

    /**
     * رفع قالب وجه موجود مسبقاً إلى الجهاز (للمزامنة)
     * POST /attendance-device/{id}/register-face
     */
    public function registerFace(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'template' => 'required|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $deviceUserId = $employee->device_user_id;
        if (!$deviceUserId) {
            return response()->json(['message' => 'الموظف ليس لديه رقم بصمة'], 422);
        }

        $templateData = base64_decode($data['template']);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->disableDevice();
        $success = $zk->enrollFaceTemplate((int)$deviceUserId, $templateData);
        $zk->enableDevice();
        $zk->disconnect();

        if ($success === false) {
            return response()->json(['message' => 'فشل تسجيل الوجه على الجهاز'], 500);
        }

        $face = Face::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'face_id' => '50',
            'template' => $data['template'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $face,
            'message' => 'تم تسجيل بصمة الوجه بنجاح'
        ]);
    }



    public function downloadFaceData($id, $employeeId = null)
    {
        $device = AttendanceDevice::findOrFail($id);

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $result = [];

        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            if ($employee->device_user_id) {
                $result = $zk->getFaceData((int)$employee->device_user_id);
            }
        } else {
            $users = $zk->getUser();
            foreach ($users as $user) {
                $result[$user['userid']] = $zk->getFaceData($user['uid']);
            }
        }

        $zk->disconnect();

        return response()->json(['data' => $result]);
    }

    public function registerEmployeeOnDevice(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employee = Employee::with(['fingerprints', 'faces'])->findOrFail($data['employee_id']);
        $deviceUserId = $employee->device_user_id;
        if (!$deviceUserId) {
            return response()->json(['message' => 'الموظف ليس لديه رقم بصمة'], 422);
        }

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $results = [];

        try {
            $zk->disableDevice();

            $zk->setUser(
                (int)$deviceUserId,
                $deviceUserId,
                $employee->name,
                $employee->phone ?? ''
            );
            $results['user_created'] = true;

            $fingerprintCount = 0;
            foreach ($employee->fingerprints as $fp) {
                if ($fp->template) {
                    $templateData = base64_decode($fp->template);
                    if ($zk->enrollFingerprint((int)$deviceUserId, (int)$fp->finger_id, $templateData)) {
                        $fingerprintCount++;
                    }
                }
            }
            $results['fingerprints_uploaded'] = $fingerprintCount;

            $faceCount = 0;
            foreach ($employee->faces as $face) {
                if ($face->template) {
                    $templateData = base64_decode($face->template);
                    if ($zk->enrollFaceTemplate((int)$deviceUserId, $templateData)) {
                        $faceCount++;
                    }
                }
            }
            $results['faces_uploaded'] = $faceCount;

            $zk->enableDevice();
            $zk->disconnect();
        } catch (\Exception $e) {
            try { $zk->disconnect(); } catch (\Exception $e2) {}
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'message' => 'تم تسجيل الموظف على الجهاز بنجاح'
        ]);
    }

    public function removeEmployeeFromDevice(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $deviceUserId = $employee->device_user_id;
        if (!$deviceUserId) {
            return response()->json(['message' => 'الموظف ليس لديه رقم بصمة'], 422);
        }

        $zk = $this->connect($device);
        if (!$zk) {
            return response()->json(['message' => 'Connection failed'], 422);
        }

        $zk->removeUser((int)$deviceUserId);
        $zk->disconnect();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الموظف من الجهاز'
        ]);
    }

    /**
     * تحقق من حالة التسجيل على الجهاز
     * إذا تم العثور على بصمة/وجه، يتم تحميل القالب وإرجاعه
     * POST /attendance-device/{id}/check-enrollment
     */


    /**
     * Start auto fingerprint enrollment on device.
     * Device will prompt user to press finger 3 times.
     */
    public function enrollFingerprint(Request $request, $id)
    {
        try {
            $device = AttendanceDevice::findOrFail($id);
            $data = $request->validate([
                'user_id' => 'required|string',
                'name' => 'nullable|string',
                'finger_id' => 'required|integer|min:0|max:15',
            ]);

            $service = new ZKTecoService();
            $result = $service->enrollFingerprint(
                $device,
                $data['user_id'],
                $data['name'] ?? $data['user_id'],
                (int) $data['finger_id']
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Start auto face enrollment on device.
     */
    public function enrollFace(Request $request, $id)
    {
        try {
            $device = AttendanceDevice::findOrFail($id);
            $data = $request->validate([
                'user_id' => 'required|string',
                'name' => 'nullable|string',
            ]);

            $service = new ZKTecoService();
            $result = $service->enrollFace(
                $device,
                $data['user_id'],
                $data['name'] ?? $data['user_id']
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if fingerprint/face was enrolled on device.
     */
    public function checkEnrollment(Request $request, $id)
    {
        try {
            $device = AttendanceDevice::findOrFail($id);
            $data = $request->validate([
                'user_id' => 'required|string',
                'finger_id' => 'nullable|integer',
            ]);

            $service = new ZKTecoService();
            $result = $service->checkEnrollment(
                $device,
                $data['user_id'],
                isset($data['finger_id']) ? (int) $data['finger_id'] : null
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['enrolled' => false, 'message' => 'خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Register user on device manually (current behavior, no auto-enroll).
     */
    public function registerUserManually(Request $request, $id)
    {
        try {
            $device = AttendanceDevice::findOrFail($id);
            $data = $request->validate([
                'user_id' => 'required|string',
                'name' => 'nullable|string',
            ]);

            $service = new ZKTecoService();
            $result = $service->registerUserOnly(
                $device,
                $data['user_id'],
                $data['name'] ?? $data['user_id']
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الموظف على الجهاز. الآن من الجهاز: Menu > إدارة المستخدمين > تسجيل البصمة > اختر الرقم ' . $data['user_id'],
                    'manual_required' => true,
                ]);
            }

            return response()->json($result, 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()], 500);
        }
    }
}
