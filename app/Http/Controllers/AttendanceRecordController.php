<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceDeviceLog;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\Setting;
use App\Models\Warning;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceRecord::with([
            'employee' => function($q) {
                $q->with('attendanceDevice');
            }, 
            'warning'
        ]);
        
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->from_date) {
            $query->where('date', '>=', $request->from_date);
        }
        
        if ($request->to_date) {
            $query->where('date', '<=', $request->to_date);
        }
        
        $records = $query->orderBy('date', 'desc')->paginate(50);
        
        // Add device info from attendance_device_logs
        $records->getCollection()->transform(function($record) {
            if ($record->employee && $record->employee->device_user_id) {
                $log = \App\Models\AttendanceDeviceLog::where('device_user_id', $record->employee->device_user_id)
                    ->whereDate('timestamp', $record->date)
                    ->first();
                
                if ($log && $log->device_id) {
                    $device = \App\Models\AttendanceDevice::find($log->device_id);
                    if ($device) {
                        $record->device_name = $device->name;
                        $record->device_host = $device->host;
                    }
                }
            }
            return $record;
        });
        
        return response()->json($records);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in_time' => 'nullable',
            'check_out_time' => 'nullable',
            'notes' => 'nullable|string',
        ]);
        
        // Use Africa/Khartoum timezone
        $tz = 'Africa/Khartoum';
        
        // Parse times properly - accept time string or datetime
        $checkInTime = null;
        $checkOutTime = null;
        
        if (!empty($data['check_in_time'])) {
            if (strpos($data['check_in_time'], '-') !== false || strpos($data['check_in_time'], 'T') !== false) {
                $checkInTime = Carbon::parse($data['check_in_time'])->setTimezone($tz);
            } else {
                $checkInTime = Carbon::parse($data['date'] . ' ' . $data['check_in_time'], $tz);
            }
        }
        
        if (!empty($data['check_out_time'])) {
            if (strpos($data['check_out_time'], '-') !== false || strpos($data['check_out_time'], 'T') !== false) {
                $checkOutTime = Carbon::parse($data['check_out_time'])->setTimezone($tz);
            } else {
                $checkOutTime = Carbon::parse($data['date'] . ' ' . $data['check_out_time'], $tz);
            }
        }
        
        $record = AttendanceRecord::updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'date' => $data['date'],
            ],
            [
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'notes' => $data['notes'] ?? null,
            ]
        );
        
        // Auto-calculate types and deductions
        if ($checkInTime || $checkOutTime) {
            $this->calculateDeductions($record);
        }
        
        // Refresh to get calculated values
        $record->refresh();
        
        return response()->json($record->load('employee'));
    }

    public function excuseDelay(Request $request, $id)
    {
        $user = $request->user();
        $permissions = $user->role?->permissions ?? [];
        $isAdmin = in_array('*', $permissions);
        
        if (!$isAdmin && !in_array('attendance.excuse', $permissions) && !in_array('attendance.manage', $permissions)) {
            return response()->json(['error' => 'ليس لديك صلاحية قبول العذر'], 403);
        }
        
        $record = AttendanceRecord::findOrFail($id);
        
        $record->update([
            'delay_excused' => true,
            'check_in_excused' => true,
            'check_in_excuse_reason' => $request->reason ?? 'عذر مقبول',
            'has_delay' => false,
            'delay_deduction' => 0,
            'total_deduction' => $record->early_leave_deduction + $record->absence_deduction,
        ]);
        
        return response()->json($record->load('employee'));
    }

    public function excuseEarlyLeave(Request $request, $id)
    {
        $user = $request->user();
        $permissions = $user->role?->permissions ?? [];
        $isAdmin = in_array('*', $permissions);
        
        if (!$isAdmin && !in_array('attendance.excuse', $permissions) && !in_array('attendance.manage', $permissions)) {
            return response()->json(['error' => 'ليس لديك صلاحية قبول العذر'], 403);
        }
        
        $record = AttendanceRecord::findOrFail($id);
        
        $record->update([
            'check_out_excused' => true,
            'check_out_excuse_reason' => $request->reason ?? 'عذر مقبول',
            'early_leave_deduction' => 0,
            'total_deduction' => $record->delay_deduction + $record->absence_deduction,
        ]);
        
        return response()->json($record->load('employee'));
    }

    public function excuseAbsence(Request $request, $id)
    {
        $user = $request->user();
        $permissions = $user->role?->permissions ?? [];
        $isAdmin = in_array('*', $permissions);
        
        if (!$isAdmin && !in_array('attendance.excuse', $permissions) && !in_array('attendance.manage', $permissions)) {
            return response()->json(['error' => 'ليس لديك صلاحية قبول العذر'], 403);
        }
        
        $record = AttendanceRecord::findOrFail($id);
        
        $record->update([
            'absence_excused' => true,
            'absence_excuse_reason' => $request->reason ?? 'عذر مقبول',
            'absence_deduction' => 0,
            'total_deduction' => $record->delay_deduction + $record->early_leave_deduction,
        ]);
        
        return response()->json($record->load('employee'));
    }

    public function cancelDeduction($id)
    {
        $record = AttendanceRecord::findOrFail($id);
        
        $record->update([
            'deduction_applied' => false,
            'total_deduction' => 0,
        ]);
        
        return response()->json($record->load('employee'));
    }

    public function applyDeduction($id)
    {
        $record = AttendanceRecord::findOrFail($id);
        
        $record->update([
            'deduction_applied' => true,
        ]);
        
        return response()->json($record->load('employee'));
    }

    public function calculateDeductions(AttendanceRecord $record)
    {
        $employee = $record->employee;
        $tz = 'Africa/Khartoum';
        
        // Get attendance settings
        $settings = Setting::where('key', 'attendance')->first();
        $attendanceSettings = $settings ? $settings->value : [];
        
        // Use defaults if settings not set or invalid
        $allowedDelayMinutes = isset($attendanceSettings['allowed_delay_minutes']) && $attendanceSettings['allowed_delay_minutes'] > 0 
            ? (int)$attendanceSettings['allowed_delay_minutes'] 
            : 5;
        $lateDeductionPercent = isset($attendanceSettings['late_deduction_percent']) && $attendanceSettings['late_deduction_percent'] > 0 
            ? (float)$attendanceSettings['late_deduction_percent'] 
            : 1.5;
        $delayBeforeWarning = isset($attendanceSettings['delay_before_warning']) && $attendanceSettings['delay_before_warning'] > 0 
            ? (int)$attendanceSettings['delay_before_warning'] 
            : 2;
        $delayBeforeDeduction = isset($attendanceSettings['delay_before_deduction']) && $attendanceSettings['delay_before_deduction'] > 0 
            ? (int)$attendanceSettings['delay_before_deduction'] 
            : 1;
        
        // Get employee's shift
        $shift = null;
        if ($employee->work_shift_id) {
            $shift = WorkShift::find($employee->work_shift_id);
        }
        
        // If no shift assigned, use default times
        $shiftStart = $shift ? $shift->start_time : '08:00';
        $shiftEnd = $shift ? $shift->end_time : '17:00';
        $expectedHours = ($shift && $shift->daily_hours) ? $shift->daily_hours : 8;
        
        // Calculate check-in type
        if ($record->check_in_time) {
            // Handle date properly
            $recordDate = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
            
            // Create shift start time in Khartoum timezone
            $shiftStartTime = Carbon::parse($recordDate . ' ' . $shiftStart, $tz);
            
            // Get actual check-in time and ensure it's in the same timezone
            $actualCheckIn = $record->check_in_time instanceof Carbon 
                ? $record->check_in_time->setTimezone($tz) 
                : Carbon::parse($record->check_in_time)->setTimezone($tz);
            
            // Compare times - diffInMinutes from shiftStart to actual gives positive if late
            $delayMinutes = $shiftStartTime->diffInMinutes($actualCheckIn);
            
            if ($actualCheckIn->lt($shiftStartTime)) {
                $record->check_in_type = 'early';
                $record->check_in_delay_minutes = 0;
            } elseif ($delayMinutes <= $allowedDelayMinutes) {
                $record->check_in_type = 'on_time';
                $record->check_in_delay_minutes = 0;
            } else {
                $record->check_in_type = 'late';
                $record->check_in_delay_minutes = $delayMinutes;
                $record->has_delay = true;
            }
        }
        
        // Count late arrivals in the month (for deduction and warning thresholds)
        $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);
        $monthStart = $recordDate->copy()->startOfMonth();
        $monthEnd = $recordDate->copy()->endOfMonth();
        
        $lateCount = AttendanceRecord::where('employee_id', $record->employee_id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('has_delay', true)
            ->where('delay_excused', false)
            ->count();
        
        // Calculate deduction if late and threshold reached
        if ($record->has_delay && !$record->delay_excused && $record->check_in_delay_minutes > $allowedDelayMinutes) {
            // Only deduct if late count >= delay_before_deduction threshold
            if ($lateCount >= $delayBeforeDeduction) {
                $baseSalary = $employee->base_salary ?? 0;
                $positionAllowance = $employee->position_allowance ?? 0;
                $transportAllowance = $employee->transport_allowance ?? 0;
                $housingAllowance = $employee->housing_allowance ?? 0;
                $foodAllowance = $employee->food_allowance ?? 0;
                $grossSalary = $baseSalary + $positionAllowance + $transportAllowance + $housingAllowance + $foodAllowance;
                
                $salary = $grossSalary > 0 ? $grossSalary : 1000;
                $hourlyRate = $salary / 240;
                $deductionMinutes = $record->check_in_delay_minutes - $allowedDelayMinutes;
                $record->delay_deduction = round($hourlyRate * ($deductionMinutes / 60) * ($lateDeductionPercent / 100), 2);
            }
        }
        
        // Calculate check-out type
        if ($record->check_out_time) {
            $recordDate = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
            $shiftEndTime = Carbon::parse($recordDate . ' ' . $shiftEnd, $tz);
            
            $actualCheckOut = $record->check_out_time instanceof Carbon 
                ? $record->check_out_time->setTimezone($tz) 
                : Carbon::parse($record->check_out_time)->setTimezone($tz);
            
            // diffInMinutes from actualCheckOut to shiftEnd gives positive if early leave
            $earlyMinutes = $actualCheckOut->diffInMinutes($shiftEndTime);
            
            if ($actualCheckOut->lt($shiftEndTime)) {
                $record->check_out_type = 'early';
                $record->check_out_early_minutes = $earlyMinutes;
                
                // Calculate early leave deduction - use gross salary
                if (!$record->check_out_excused) {
                    $baseSalary = $employee->base_salary ?? 0;
                    $positionAllowance = $employee->position_allowance ?? 0;
                    $transportAllowance = $employee->transport_allowance ?? 0;
                    $housingAllowance = $employee->housing_allowance ?? 0;
                    $foodAllowance = $employee->food_allowance ?? 0;
                    $grossSalary = $baseSalary + $positionAllowance + $transportAllowance + $housingAllowance + $foodAllowance;
                    
                    $salary = $grossSalary > 0 ? $grossSalary : 1000;
                    $hourlyRate = $salary / 240;
                    $record->early_leave_deduction = round($hourlyRate * ($record->check_out_early_minutes / 60), 2);
                }
            } elseif ($actualCheckOut->gt($shiftEndTime)) {
                $record->check_out_type = 'late';
            } else {
                $record->check_out_type = 'on_time';
            }
        }
        
        // Calculate worked hours
        if ($record->check_in_time && $record->check_out_time) {
            $checkIn = $record->check_in_time instanceof Carbon 
                ? $record->check_in_time->setTimezone($tz) 
                : Carbon::parse($record->check_in_time)->setTimezone($tz);
            $checkOut = $record->check_out_time instanceof Carbon 
                ? $record->check_out_time->setTimezone($tz) 
                : Carbon::parse($record->check_out_time)->setTimezone($tz);
            $worked = $checkIn->diffInMinutes($checkOut);
            $record->worked_hours = $worked / 60;
        }
        $record->expected_hours = $expectedHours;
        
        // Calculate total deduction
        $record->total_deduction = ($record->delay_deduction ?? 0) + ($record->early_leave_deduction ?? 0);
        
        // Issue warning if late count >= delay_before_warning threshold
        if ($record->has_delay && !$record->delay_excused && $lateCount >= $delayBeforeWarning && !$record->warning_issued) {
            $record->warning_issued = true;
            
            // Ensure recordDate is a Carbon object for toDateString()
            $warningDate = $record->date instanceof Carbon ? $record->date->toDateString() : Carbon::parse($record->date)->toDateString();
            
            // Create warning record
            $warning = Warning::create([
                'employee_id' => $record->employee_id,
                'type' => 'تأخير متكرر',
                'reason' => 'تأخر ' . $lateCount . ' مرات خلال الشهر',
                'date' => $warningDate,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
            
            $record->warning_id = $warning->id;
        }
        
        $record->save();
    }

    // Process attendance from device logs
    public function processFromDeviceLogs(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?? now()->format('Y-m-d');
        $employeeId = $request->employee_id;
        $deviceId = $request->device_id;
        
        // Get settings
        $settings = Setting::where('key', 'attendance')->first();
        $attendanceSettings = $settings ? $settings->value : [];
        $allowedDelayMinutes = $attendanceSettings['allowed_delay_minutes'] ?? 5;
        
        $query = AttendanceDeviceLog::whereBetween('timestamp', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
        
        if ($employeeId) {
            $query->where('device_user_id', $employeeId);
        }
        
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }
        
        $logs = $query->orderBy('timestamp')->get();
        
        // Group by employee and date
        $groupedLogs = $logs->groupBy(function($log) {
            return $log->device_user_id . '_' . Carbon::parse($log->timestamp)->format('Y-m-d');
        });
        
        $results = [];
        
        foreach ($groupedLogs as $key => $userLogs) {
            $parts = explode('_', $key);
            $deviceUserId = $parts[0];
            $date = $parts[1];
            
            // Find employee by device_user_id
            $employee = Employee::where('device_user_id', $deviceUserId)->first();
            if (!$employee) continue;
            
            // Sort by timestamp
            $sortedLogs = $userLogs->sortBy(function($log) {
                return $log->timestamp;
            });
            
            // For single-button devices: first record = check-in, last record = check-out
            $firstLog = $sortedLogs->first();
            $lastLog = $sortedLogs->last();
            
            // If there's only one record, use it as check-in
            $checkIn = $firstLog;
            $checkOut = ($sortedLogs->count() > 1) ? $lastLog : null;
            
            // Ensure check-out is after check-in
            if ($checkOut && $checkIn && Carbon::parse($checkOut->timestamp)->lt(Carbon::parse($checkIn->timestamp))) {
                $checkOut = null; // Invalid checkout time
            }
            
            $record = AttendanceRecord::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $date],
                [
                    'check_in_time' => $checkIn ? Carbon::parse($checkIn->timestamp)->setTimezone('Africa/Khartoum') : null,
                    'check_out_time' => $checkOut ? Carbon::parse($checkOut->timestamp)->setTimezone('Africa/Khartoum') : null,
                ]
            );
            
            $this->calculateDeductions($record);
            
            $results[] = $record;
        }
        
        return response()->json([
            'processed' => count($results),
            'records' => $results,
        ]);
    }

    // Get monthly report
    public function monthlyReport(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;
        $departmentId = $request->department_id;
        
        $query = AttendanceRecord::with(['employee.department'])
            ->whereMonth('date', $month)
            ->whereYear('date', $year);
        
        if ($departmentId) {
            $query->whereHas('employee', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }
        
        $records = $query->get();
        
        // Group by employee
        $employeeStats = $records->groupBy('employee_id')->map(function($records, $employeeId) {
            $employee = $records->first()->employee;
            
            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'total_days' => $records->count(),
                'present_days' => $records->whereNotNull('check_in_time')->count(),
                'absent_days' => $records->whereNull('check_in_time')->count(),
                'late_days' => $records->where('check_in_type', 'late')->count(),
                'early_days' => $records->where('check_in_type', 'early')->count(),
                'on_time_days' => $records->where('check_in_type', 'on_time')->count(),
                'total_delay_minutes' => $records->sum('check_in_delay_minutes'),
                'total_deduction' => $records->sum('total_deduction'),
                'deductions_applied' => $records->where('deduction_applied', true)->sum('total_deduction'),
                'warnings_issued' => $records->where('warning_issued', true)->count(),
            ];
        });
        
        return response()->json([
            'data' => $employeeStats->values(),
            'month' => $month,
            'year' => $year,
        ]);
    }
    
    public function recalculateAll(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            
            $records = AttendanceRecord::with('employee')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get();
            
            $recalculated = 0;
            $errors = [];
            
            foreach ($records as $record) {
                try {
                    if (!$record->employee) {
                        $errors[] = "Record #{$record->id}: No employee found";
                        continue;
                    }
                    $this->calculateDeductions($record);
                    $recalculated++;
                } catch (\Exception $e) {
                    $errors[] = "Record #{$record->id}: " . $e->getMessage();
                }
            }
            
            return response()->json([
                'message' => 'تم إعادة حساب ' . $recalculated . ' سجل',
                'recalculated' => $recalculated,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('RecalculateAll Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إعادة الحساب: ' . $e->getMessage()], 500);
        }
    }

    // تصدير PDF لسجلات الحضور
    public function exportPdf(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?? now()->format('Y-m-d');
        
        $records = AttendanceRecord::with(['employee.attendanceDevice'])
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date', 'desc')
            ->get();
        
        // Build HTML table
        $html = '<html dir="rtl"><head><meta charset="UTF-8"><style>
            body { font-family: Arial, sans-serif; direction: rtl; }
            h1 { text-align: center; color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #4a5568; color: white; padding: 10px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; text-align: center; }
            tr:nth-child(even) { background: #f9f9f9; }
            .late { color: #e53e3e; font-weight: bold; }
            .early { color: #3182ce; }
            .on-time { color: #38a169; }
            .header-info { text-align: center; margin-bottom: 20px; }
        </style></head><body>';
        
        $orgSetting = \App\Models\Setting::where('key', 'organization')->first();
        $orgData = [];
        if ($orgSetting) {
            if (is_array($orgSetting->value)) {
                $orgData = $orgSetting->value;
            } elseif (is_string($orgSetting->value)) {
                $orgData = json_decode($orgSetting->value, true) ?? [];
            }
        }
        $orgName = $orgData['name'] ?? 'مؤسسة Jawda HR';
        $html .= '<h1>' . $orgName . '</h1>';
        $html .= '<h2>تقرير سجلات الحضور والانصراف</h2>';
        $html .= '<div class="header-info"><p>من تاريخ: ' . $fromDate . ' | إلى تاريخ: ' . $toDate . '</p></div>';
        
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>#</th><th>اسم الموظف</th><th>رقم البصمة</th><th>التاريخ</th>';
        $html .= '<th>وقت الدخول</th><th>نوع الدخول</th><th>وقت الانصراف</th><th>نوع الانصراف</th>';
        $html .= '<th>دقائق التأخير</th><th>الخصم</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($records as $index => $record) {
            $checkInTypeClass = $record->check_in_type == 'late' ? 'late' : ($record->check_in_type == 'early' ? 'early' : 'on-time');
            $checkInLabel = $this->getTypeLabel($record->check_in_type);
            $checkOutLabel = $this->getTypeLabel($record->check_out_type);
            
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . ($record->employee->name ?? '-') . '</td>';
            $html .= '<td>' . ($record->employee->device_user_id ?? '-') . '</td>';
            $html .= '<td>' . $record->date . '</td>';
            $html .= '<td>' . ($record->check_in_time ? Carbon::parse($record->check_in_time)->format('H:i:s') : '-') . '</td>';
            $html .= '<td class="' . $checkInTypeClass . '">' . $checkInLabel . '</td>';
            $html .= '<td>' . ($record->check_out_time ? Carbon::parse($record->check_out_time)->format('H:i:s') : '-') . '</td>';
            $html .= '<td>' . $checkOutLabel . '</td>';
            $html .= '<td>' . ($record->check_in_delay_minutes ?: '-') . '</td>';
            $html .= '<td>' . ($record->total_deduction > 0 ? number_format($record->total_deduction, 2) . ' SDG' : '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '<p style="text-align:center; margin-top:30px; color:#666;">Generated: ' . now()->format('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';
        
        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
    
    private function getTypeLabel($type)
    {
        $labels = [
            'on_time' => 'في الوقت',
            'late' => 'متأخر',
            'early' => 'مبكر',
        ];
        return $labels[$type] ?? '-';
    }
}
