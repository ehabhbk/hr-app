<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceDeviceLogsController extends Controller
{
    /**
     * عرض كل السجلات مع دعم فلترة
     */
    public function index(Request $request)
    {
        $query = AttendanceDeviceLog::query()->orderBy('timestamp', 'desc');

        if ($request->filled('from')) {
            $query->where('timestamp', '>=', $request->input('from').' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('timestamp', '<=', $request->input('to').' 23:59:59');
        }
        if ($request->filled('employee_id')) {
            $query->where('device_user_id', 'like', '%'.$request->input('employee_id').'%');
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        $logs = $query->paginate(100);

        // Enrich logs with employee names and device info
        $logs->getCollection()->transform(function ($log) {
            // Find employee by device_user_id
            $employee = Employee::where('device_user_id', $log->device_user_id)->first();

            // Find device
            $device = $log->device_id ? AttendanceDevice::find($log->device_id) : null;

            // Determine attendance type based on state
            // State: 1=حضور, 2=حضور مبكر, 3=حضور متأخر, 4=انصراف, 5=انصراف مبكر, 6=انصراف متأخر
            $state = $log->state ?? 1;
            
            // Map state to type string
            $typeMap = [
                1 => 'attendance',
                2 => 'attendance_early',
                3 => 'attendance_late',
                4 => 'checkout',
                5 => 'checkout_early',
                6 => 'checkout_late',
            ];
            
            $type = $typeMap[$state] ?? 'attendance';

            return [
                'id' => $log->id,
                'device_user_id' => $log->device_user_id,
                'employee_id' => $employee ? $employee->id : null,
                'employee_name' => $employee ? $employee->name : null,
                'device_id' => $log->device_id,
                'device_name' => $device ? $device->name : null,
                'device_host' => $device ? $device->host : null,
                'timestamp' => $log->timestamp,
                'type' => $type,
                'state' => $state,
                'raw' => $log->raw,
            ];
        });

        return response()->json(['data' => $logs], 200);
    }

    /**
     * إضافة سجل جديد (يدويًا أو للاختبار)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'uid' => 'nullable|integer',
            'device_user_id' => 'required|string',
            'state' => 'nullable|integer',
            'timestamp' => 'required|date',
            'raw' => 'nullable|json',
        ]);

        $time = Carbon::parse($validated['timestamp'])->toDateTimeString();

        $recentExists = AttendanceDeviceLog::where('device_user_id', $validated['device_user_id'])
            ->whereBetween('timestamp', [
                Carbon::parse($time)->subMinutes(10)->toDateTimeString(),
                Carbon::parse($time)->addMinutes(10)->toDateTimeString(),
            ])
            ->exists();

        if ($recentExists) {
            return response()->json(['message' => 'توجد بصمة مسجلة خلال 10 دقائق من هذا التوقيت'], 422);
        }

        $validated['timestamp'] = $time;
        $log = AttendanceDeviceLog::create($validated);

        return response()->json(['message' => 'تمت إضافة السجل بنجاح', 'data' => $log], 201);
    }

    /**
     * عرض سجل محدد
     */
    public function show(AttendanceDeviceLog $attendanceDeviceLog)
    {
        return response()->json(['data' => $attendanceDeviceLog], 200);
    }

    /**
     * تحديث سجل محدد
     */
    public function update(Request $request, AttendanceDeviceLog $attendanceDeviceLog)
    {
        $validated = $request->validate([
            'uid' => 'nullable|integer',
            'device_user_id' => 'required|string',
            'state' => 'nullable|integer',
            'timestamp' => 'required|date',
            'raw' => 'nullable|json',
        ]);

        $attendanceDeviceLog->update($validated);

        return response()->json(['message' => 'تم تحديث السجل بنجاح', 'data' => $attendanceDeviceLog], 200);
    }

    /**
     * حذف سجل محدد
     */
    public function destroy(AttendanceDeviceLog $attendanceDeviceLog)
    {
        $attendanceDeviceLog->delete();

        return response()->json(['message' => 'تم حذف السجل بنجاح'], 200);
    }

    /**
     * قبول عذر للتسجيل من الجهاز
     */
    public function excuse(Request $request, $id)
    {
        $log = AttendanceDeviceLog::findOrFail($id);
        
        // Find employee
        $employee = Employee::where('device_user_id', $log->device_user_id)->first();
        
        if (!$employee) {
            return response()->json(['message' => 'لم يتم العثور على الموظف'], 404);
        }
        
        // Get date from timestamp
        $tz = 'Africa/Khartoum';
        $timestamp = $log->timestamp instanceof \Carbon\Carbon 
            ? $log->timestamp->setTimezone($tz)
            : \Carbon\Carbon::parse($log->timestamp)->setTimezone($tz);
        $date = $timestamp->toDateString();
        
        // Find or create attendance record for this day
        $record = \App\Models\AttendanceRecord::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date,
            ],
            [
                'check_in_time' => $log->state <= 3 ? $timestamp : null,
                'check_out_time' => $log->state >= 4 ? $timestamp : null,
            ]
        );
        
        // Apply excuse
        $record->update([
            'delay_excused' => true,
            'check_in_excused' => true,
            'check_in_excuse_reason' => $request->reason ?? 'عذر مقبول',
            'has_delay' => false,
            'delay_deduction' => 0,
            'total_deduction' => 0,
        ]);
        
        return response()->json([
            'message' => 'تم قبول العذر بنجاح',
            'record' => $record->load('employee'),
        ]);
    }
}
