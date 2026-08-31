<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceDeviceLog;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\Setting;
use App\Models\Warning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        
        // Support month parameter (YYYY-MM format) for calendar page
        if ($request->month && !$request->from_date && !$request->to_date) {
            $monthStart = Carbon::parse($request->month . '-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $query->where('date', '>=', $monthStart->toDateString());
            $query->where('date', '<=', $monthEnd->toDateString());
        }
        
        if ($request->from_date) {
            $query->where('date', '>=', $request->from_date);
        }
        
        if ($request->to_date) {
            $query->where('date', '<=', $request->to_date);
        }
        
        $perPage = $request->input('per_page', 50);
        $records = $query->orderBy('date', 'desc')->paginate($perPage);
        
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
            'check_in_type' => 'nullable|string|in:early,on_time,late',
            'check_out_type' => 'nullable|string|in:early,on_time,late',
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
        
        // Apply manual type overrides after calculation
        $typeUpdates = [];
        if (!empty($data['check_in_type'])) {
            $typeUpdates['check_in_type'] = $data['check_in_type'];
        }
        if (!empty($data['check_out_type'])) {
            $typeUpdates['check_out_type'] = $data['check_out_type'];
        }
        if ($typeUpdates) {
            $record->update($typeUpdates);
        }
        
        // Refresh to get calculated values
        $record->refresh();
        
        return response()->json($record->load('employee'));
    }

    public function update(Request $request, $id)
    {
        $record = AttendanceRecord::findOrFail($id);

        $data = $request->validate([
            'check_in_time' => 'nullable',
            'check_out_time' => 'nullable',
            'check_in_type' => 'nullable|string|in:early,on_time,late',
            'check_out_type' => 'nullable|string|in:early,on_time,late',
            'notes' => 'nullable|string',
        ]);

        $tz = 'Africa/Khartoum';
        $updates = [];

        $dateStr = is_string($record->date) ? substr($record->date, 0, 10) : $record->date->format('Y-m-d');

        if (array_key_exists('check_in_time', $data)) {
            if (empty($data['check_in_time'])) {
                $updates['check_in_time'] = null;
                $updates['check_in_type'] = null;
                $updates['has_delay'] = false;
                $updates['check_in_delay_minutes'] = 0;
                $updates['delay_deduction'] = 0;
                $updates['delay_excused'] = false;
            } else {
                if (strpos($data['check_in_time'], 'T') !== false) {
                    $updates['check_in_time'] = Carbon::parse($data['check_in_time'])->setTimezone($tz);
                } else {
                    $updates['check_in_time'] = Carbon::parse($dateStr . ' ' . $data['check_in_time'], $tz);
                }
            }
        }

        if (array_key_exists('check_out_time', $data)) {
            if (empty($data['check_out_time'])) {
                $updates['check_out_time'] = null;
                $updates['check_out_type'] = null;
                $updates['check_out_early_minutes'] = 0;
                $updates['early_leave_deduction'] = 0;
                $updates['check_out_excused'] = false;
            } else {
                if (strpos($data['check_out_time'], 'T') !== false) {
                    $updates['check_out_time'] = Carbon::parse($data['check_out_time'])->setTimezone($tz);
                } else {
                    $updates['check_out_time'] = Carbon::parse($dateStr . ' ' . $data['check_out_time'], $tz);
                }
            }
        }

        $manualCheckInType = null;
        $manualCheckOutType = null;

        if (!empty($data['check_in_type'])) {
            $manualCheckInType = $data['check_in_type'];
            $updates['check_in_type'] = $data['check_in_type'];
            if ($data['check_in_type'] === 'late' && $record->check_in_delay_minutes > 0) {
                $updates['has_delay'] = true;
            } elseif ($data['check_in_type'] === 'on_time' || $data['check_in_type'] === 'early') {
                $updates['has_delay'] = false;
                $updates['delay_deduction'] = 0;
            }
        }

        if (!empty($data['check_out_type'])) {
            $manualCheckOutType = $data['check_out_type'];
            $updates['check_out_type'] = $data['check_out_type'];
        }

        if (isset($data['notes'])) {
            $updates['notes'] = $data['notes'];
        }

        $record->update($updates);

        $this->calculateDeductions($record, $manualCheckInType, $manualCheckOutType);

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

    public function calculateDeductions(AttendanceRecord $record, ?string $manualCheckInType = null, ?string $manualCheckOutType = null)
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
        
        // Get employee's shift (supports rotation)
        $shift = null;
        $recordDate = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
        $effectiveShiftId = $employee->getEffectiveShiftForDate($recordDate);
        if ($effectiveShiftId) {
            $shift = WorkShift::find($effectiveShiftId);
        } elseif ($employee->work_shift_id) {
            $shift = WorkShift::find($employee->work_shift_id);
        }
        
        // If no shift assigned, find the nearest shift based on check-in time
        if (!$shift && $record->check_in_time) {
            $allShifts = WorkShift::where('active', true)->get();
            if ($allShifts->isNotEmpty()) {
                $recordDate = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
                $actualCheckIn = $record->check_in_time instanceof Carbon
                    ? $record->check_in_time->setTimezone($tz)
                    : Carbon::parse($record->check_in_time)->setTimezone($tz);
                
                $nearestShift = null;
                $minDiff = PHP_INT_MAX;
                
                foreach ($allShifts as $s) {
                    $shiftStartDT = Carbon::parse($recordDate . ' ' . $s->start_time, $tz);
                    $diff = abs($actualCheckIn->diffInMinutes($shiftStartDT));
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $nearestShift = $s;
                    }
                }
                
                $shift = $nearestShift;
            }
        }
        
        // If still no shift found, use default times
        $shiftStart = $shift ? $shift->start_time : '08:00';
        $shiftEnd = $shift ? $shift->end_time : '17:00';
        $expectedHours = ($shift && $shift->daily_hours) ? $shift->daily_hours : 8;
        
        // Calculate check-in type
        if ($record->check_in_time && !$manualCheckInType) {
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
        
        // Check if absence rules are enabled (controls deductions & warnings)
        $rulesEnabled = $attendanceSettings['absence_rules_enabled'] ?? false;
        
        // Count late arrivals in the month (for deduction and warning thresholds)
        $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);
        $monthStart = $recordDate->copy()->startOfMonth();
        $monthEnd = $recordDate->copy()->endOfMonth();
        
        $lateCount = AttendanceRecord::where('employee_id', $record->employee_id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('has_delay', true)
            ->where('delay_excused', false)
            ->count();
        
        // Deductions & warnings only apply when rules are enabled
        if ($rulesEnabled) {
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
            
            // Issue warning if late count >= delay_before_warning threshold
            if ($record->has_delay && !$record->delay_excused && $lateCount >= $delayBeforeWarning && !$record->warning_issued) {
                $record->warning_issued = true;
                
                $warningDate = $record->date instanceof Carbon ? $record->date->toDateString() : Carbon::parse($record->date)->toDateString();
                
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
        }

        // Send WhatsApp notifications after deduction is calculated
        if ($record->has_delay) {
            $recordDateStr = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
            try {
                $whatsapp = new \App\Services\WhatsAppService();
                // Employee notification with deduction amount
                $whatsapp->sendLateArrivalNotification($employee, $recordDateStr, $record->check_in_delay_minutes, $record->delay_deduction ?? 0);
                // Admin notification
                $whatsapp->notifyAdminLate($employee, $recordDateStr, $record->check_in_delay_minutes);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp notification error: ' . $e->getMessage());
            }
        }

        // Calculate check-out type (always runs, not a deduction)
        if ($record->check_out_time && !$manualCheckOutType) {
            $recordDate = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
            $shiftEndTime = Carbon::parse($recordDate . ' ' . $shiftEnd, $tz);
            
            // For overnight shifts: if end_time < start_time, the shift ends the next day
            if ($shift && ($shift->is_overnight || $shiftEnd < $shiftStart)) {
                $shiftEndTime->addDay();
            }
            
            $actualCheckOut = $record->check_out_time instanceof Carbon 
                ? $record->check_out_time->setTimezone($tz) 
                : Carbon::parse($record->check_out_time)->setTimezone($tz);
            
            $earlyMinutes = $actualCheckOut->diffInMinutes($shiftEndTime);
            
            if ($actualCheckOut->lt($shiftEndTime)) {
                $record->check_out_type = 'early';
                $record->check_out_early_minutes = $earlyMinutes;
                
                // Early leave deduction only when rules enabled
                if ($rulesEnabled && !$record->check_out_excused) {
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
        
        // Calculate worked hours (always runs)
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
        
        // Calculate total deduction (absence_deduction always included, delay/early only if rules enabled)
        $record->total_deduction = ($record->delay_deduction ?? 0) + ($record->early_leave_deduction ?? 0) + ($record->absence_deduction ?? 0);
        
        $record->save();
    }

    // Calculate absences for employees with shifts
    public function calculateAbsencesForPeriod($fromDate, $toDate)
    {
        $tz = 'Africa/Khartoum';
        
        // Get attendance settings
        $settings = Setting::where('key', 'attendance')->first();
        $attendanceSettings = $settings ? $settings->value : [];
        $absenceAfterMinutes = isset($attendanceSettings['absence_after_minutes']) 
            ? (int) $attendanceSettings['absence_after_minutes'] 
            : 60;
        
        $employees = Employee::where(function($q) {
            $q->whereNotNull('work_shift_id')
              ->orWhereNotNull('rotation_shift_ids')
              ->orWhereNotNull('rotation_group_id');
        })->with('workShift', 'leaves', 'rotationGroup')->get();

        $processed = 0;
        $alreadyAbsent = 0;

        foreach ($employees as $employee) {
            // Get effective shift for the first day to check basic shift validity
            $effectiveShiftId = $employee->getEffectiveShiftForDate($fromDate);
            $shift = $effectiveShiftId ? WorkShift::find($effectiveShiftId) : $employee->workShift;
            if (!$shift || !$shift->week_days || !is_array($shift->week_days)) continue;

            $workDays = $shift->week_days;
            $dailyHours = $shift->daily_hours ?? 8;
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Get effective shift for this specific date (supports rotation)
                $dayShiftId = $employee->getEffectiveShiftForDate($date);
                $dayShift = $dayShiftId ? WorkShift::find($dayShiftId) : $shift;
                if (!$dayShift || !$dayShift->week_days || !is_array($dayShift->week_days)) continue;

                $dayOfWeek = (int) $date->format('w'); // 0=Sunday, 6=Saturday
                if (!in_array($dayOfWeek, $dayShift->week_days)) continue;

                $dateStr = $date->format('Y-m-d');

                // Skip if employee has approved leave covering this day
                $activeLeave = $employee->leaves->first(function($leave) use ($dateStr) {
                    return $leave->status === 'approved'
                        && $leave->from_date->format('Y-m-d') <= $dateStr
                        && $leave->to_date->format('Y-m-d') >= $dateStr;
                });
                if ($activeLeave) continue;

                // Skip if employee has approved travel mission covering this day
                $activeTravel = \App\Models\TravelRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('from_date', '<=', $dateStr)
                    ->where('to_date', '>=', $dateStr)
                    ->exists();
                if ($activeTravel) continue;

                // Skip if employee is not working this day (rotation group)
                if (!$employee->isWorkingOnDate($date)) continue;

                // Find existing attendance record
                $record = AttendanceRecord::where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();

                if ($record) {
                    if ($record->check_in_time || $record->check_out_time) continue;
                    if ($record->is_absent) {
                        $alreadyAbsent++;
                        continue;
                    }
                }

                // Check if we're still within the absence grace period
                // Only mark absent if current time is past shift start + absence_after_minutes
                $now = Carbon::now($tz);
                $shiftStartTime = Carbon::parse($dateStr . ' ' . $dayShift->start_time, $tz);
                $absenceDeadline = $shiftStartTime->copy()->addMinutes($absenceAfterMinutes);
                
                // For past dates, always mark absent. For today, check the deadline.
                if ($dateStr === $now->format('Y-m-d') && $now->lt($absenceDeadline)) {
                    continue; // Still within grace period for today
                }

                // Calculate absence deduction
                $baseSalary = (float) ($employee->base_salary ?? 0);
                $positionAllowance = (float) ($employee->position_allowance ?? 0);
                $transportAllowance = (float) ($employee->transport_allowance ?? 0);
                $housingAllowance = (float) ($employee->housing_allowance ?? 0);
                $foodAllowance = (float) ($employee->food_allowance ?? 0);
                $grossSalary = $baseSalary + $positionAllowance + $transportAllowance + $housingAllowance + $foodAllowance;
                $salary = $grossSalary > 0 ? $grossSalary : 1000;
                $hourlyRate = $salary / 240;
                $absenceDeduction = round($hourlyRate * $dailyHours, 2);

                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $dateStr],
                    [
                        'is_absent' => true,
                        'absence_days' => 1,
                        'absence_deduction' => $absenceDeduction,
                        'absence_excused' => false,
                        'expected_hours' => $dailyHours,
                        'total_deduction' => $absenceDeduction,
                    ]
                );

                $processed++;

                // Admin notification for absence
                try {
                    $whatsapp = new \App\Services\WhatsAppService();
                    $whatsapp->notifyAdminAbsence($employee, $dateStr);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
                }
            }
        }

        return ['new' => $processed, 'existing' => $alreadyAbsent, 'total' => $processed + $alreadyAbsent];
    }

    public function calculateAbsences(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?? now()->format('Y-m-d');
        $result = $this->calculateAbsencesForPeriod($fromDate, $toDate);
        $total = $result['total'];
        $new = $result['new'];
        $existing = $result['existing'];
        $message = $total > 0
            ? "تم احتساب $total غياب" . ($new > 0 ? " ($new جديد، $existing مسبقاً)" : " (مسبقاً)")
            : "لم يتم احتساب أي غياب";
        return response()->json([
            'message' => $message,
            'absences_count' => $total,
            'new_absences' => $new,
            'existing_absences' => $existing,
        ]);
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
        $fingerprintMode = $attendanceSettings['fingerprint_mode'] ?? 'auto_in_out';
        
        $query = AttendanceDeviceLog::where('timestamp', '>=', $fromDate . ' 00:00:00')
            ->where('timestamp', '<=', $toDate . ' 23:59:59')
            ->whereNull('processed_at');
        
        if ($employeeId) {
            $query->where('device_user_id', $employeeId);
        }
        
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }
        
        $logs = $query->orderBy('timestamp')->get();
        
        if ($fingerprintMode === 'auto_in_out') {
            // Smart mode: first punch = check-in, second = check-out, alternating
            return $this->processAutoInOut($logs, $fromDate, $toDate, $allowedDelayMinutes);
        }
        
        // Legacy mode: group by employee+date, first = check-in, last = check-out
        return $this->processLegacyMode($logs, $fromDate, $toDate, $allowedDelayMinutes);
    }
    
    // Smart fingerprint mode: per-day processing with debounce
    // Each day: first punch = check-in, second = check-out
    private function processAutoInOut($logs, $fromDate, $toDate, $allowedDelayMinutes)
    {
        $tz = 'Africa/Khartoum';
        $results = [];
        $debounceMinutes = 15; // أقل من ربع ساعة بين بصمتين تُتجاهل البصمة الثانية
        $processedLogIds = [];
        
        // Group by employee
        $logsByEmployee = $logs->groupBy('device_user_id');
        
        foreach ($logsByEmployee as $deviceUserId => $employeeLogs) {
            $employee = Employee::where('device_user_id', $deviceUserId)->first();
            if (!$employee) {
                $processedLogIds = array_merge($processedLogIds, $employeeLogs->pluck('id')->toArray());
                continue;
            }
            
            // Group by date
            $logsByDate = $employeeLogs->groupBy(fn($log) => Carbon::parse($log->timestamp)->setTimezone($tz)->format('Y-m-d'));
            
            // Carry over the employee's most recent open session from the database.
            // Because the scheduler processes day-by-day (from_date = to_date = today),
            // a fresh run has no in-memory memory of an open check-in, so a punch that
            // follows an open check-in (e.g. attended 9 PM today and punched out 8 AM
            // tomorrow, or checked in at 8 AM and punched out at 4 PM) would otherwise
            // wrongly become another check-in instead of a check-out.
            $openCheckInRecord = AttendanceRecord::where('employee_id', $employee->id)
                ->whereNull('check_out_time')
                ->where('date', '<=', $fromDate)
                ->orderBy('date', 'desc')
                ->first();
            
            $dates = array_keys($logsByDate->toArray());
            sort($dates);
            
            foreach ($dates as $date) {
                $dayLogs = $logsByDate[$date];
                if ($date < $fromDate || $date > $toDate) {
                    $processedLogIds = array_merge($processedLogIds, $dayLogs->pluck('id')->toArray());
                    continue;
                }
                
                $sortedLogs = $dayLogs->sortBy(fn($log) => $log->timestamp);
                
                // Debounce within this day
                $filteredLogs = [];
                $lastAcceptedTime = null;
                
                foreach ($sortedLogs as $log) {
                    $logTime = Carbon::parse($log->timestamp)->setTimezone($tz);
                    
                    if ($lastAcceptedTime) {
                        $diffMinutes = $lastAcceptedTime->diffInMinutes($logTime);
                        if ($diffMinutes < $debounceMinutes) {
                            $processedLogIds[] = $log->id;
                            continue;
                        }
                    }
                    
                    $filteredLogs[] = $log;
                    $lastAcceptedTime = $logTime;
                }
                
                if (empty($filteredLogs)) continue;
                
                // Alternating rule: each accepted punch toggles the open state.
                // If there is an open check-in, this punch becomes a checkout, even if it
                // happens on the next day (e.g. punched attendance today and only punches
                // again tomorrow -> tomorrow's first punch is a checkout).
                // Otherwise this punch opens a new check-in record for its own date.
                foreach ($filteredLogs as $punch) {
                    $punchTime = Carbon::parse($punch->timestamp)->setTimezone($tz);
                    $punchDate = $punchTime->format('Y-m-d');

                    // Mark as processed
                    $processedLogIds[] = $punch->id;

                    if ($openCheckInRecord) {
                        // The fingerprint that follows an attendance/check-in is a checkout,
                        // even if it is on the next day.
                        $openCheckInRecord->check_out_time = $punchTime;
                        $openCheckInRecord->save();
                        $this->calculateDeductions($openCheckInRecord);
                        $results[] = $openCheckInRecord;
                        $openCheckInRecord = null;
                    } else {
                        // Fresh check-in for this punch's date, kept open until the next punch.
                        $record = AttendanceRecord::updateOrCreate(
                            ['employee_id' => $employee->id, 'date' => $punchDate],
                            ['check_in_time' => $punchTime]
                        );
                        $this->calculateDeductions($record);
                        $results[] = $record;
                        $openCheckInRecord = $record;
                    }
                }
            }
            
            // If there's still an open record at the end, just keep it
        }
        
        // Mark all processed logs (ignore unique constraint conflicts from concurrent sync)
        if (!empty($processedLogIds)) {
            $now = now()->toDateTimeString();
            foreach (array_chunk($processedLogIds, 50) as $chunk) {
                try {
                    DB::table('attendance_device_logs')
                        ->whereIn('id', $chunk)
                        ->whereNull('processed_at')
                        ->update(['processed_at' => $now]);
                } catch (\Exception $e) {
                    \Log::error('Failed to mark device logs as processed: ' . $e->getMessage());
                }
            }
        }
        
        $absencesResult = $this->calculateAbsencesForPeriod($fromDate, $toDate);
        
        return response()->json([
            'processed' => count($results),
            'absences_marked' => $absencesResult['total'],
            'records' => $results,
        ]);
    }
    
    // Legacy mode: first record per day = check-in, last = check-out
    private function processLegacyMode($logs, $fromDate, $toDate, $allowedDelayMinutes)
    {
        $processedLogIds = [];
        
        $groupedLogs = $logs->groupBy(function($log) {
            return $log->device_user_id . '_' . Carbon::parse($log->timestamp)->format('Y-m-d');
        });
        
        $results = [];
        
        foreach ($groupedLogs as $key => $userLogs) {
            $parts = explode('_', $key);
            $deviceUserId = $parts[0];
            $date = $parts[1];
            
            $employee = Employee::where('device_user_id', $deviceUserId)->first();
            if (!$employee) {
                $processedLogIds = array_merge($processedLogIds, $userLogs->pluck('id')->toArray());
                continue;
            }
            
            $sortedLogs = $userLogs->sortBy(fn($log) => $log->timestamp);
            
            $firstLog = $sortedLogs->first();
            $lastLog = $sortedLogs->last();
            
            $checkIn = $firstLog;
            $checkOut = ($sortedLogs->count() > 1) ? $lastLog : null;
            
            if ($checkOut && $checkIn && Carbon::parse($checkOut->timestamp)->lt(Carbon::parse($checkIn->timestamp))) {
                $checkOut = null;
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
            $processedLogIds = array_merge($processedLogIds, $userLogs->pluck('id')->toArray());
        }
        
        // Mark all processed logs
        if (!empty($processedLogIds)) {
            $now = now()->toDateTimeString();
            foreach (array_chunk($processedLogIds, 50) as $chunk) {
                try {
                    DB::table('attendance_device_logs')
                        ->whereIn('id', $chunk)
                        ->whereNull('processed_at')
                        ->update(['processed_at' => $now]);
                } catch (\Exception $e) {
                    \Log::error('Failed to mark device logs as processed (legacy): ' . $e->getMessage());
                }
            }
        }
        
        // Post-process overnight shifts
        $employeeIds = array_unique(array_map(fn($r) => $r->employee_id, $results));
        foreach ($employeeIds as $empId) {
            $empRecords = collect($results)->where('employee_id', $empId)->sortBy('date')->values();
            
            for ($i = 0; $i < $empRecords->count() - 1; $i++) {
                $current = $empRecords[$i];
                $next = $empRecords[$i + 1];
                
                if ($current->check_in_time && !$current->check_out_time && $next->check_in_time) {
                    $nextDate = $next->date instanceof Carbon ? $next->date->format('Y-m-d') : $next->date;
                    $currentDate = $current->date instanceof Carbon ? $current->date->format('Y-m-d') : $current->date;
                    
                    $currentDateObj = Carbon::parse($currentDate);
                    $nextDateObj = Carbon::parse($nextDate);
                    
                    if ($nextDateObj->diffInDays($currentDateObj) === 1) {
                        $nextCheckIn = $next->check_in_time;
                        $current->check_out_time = $nextCheckIn;
                        $current->save();
                        $next->check_in_time = null;
                        $next->check_out_time = null;
                        $next->save();
                        $this->calculateDeductions($current);
                    }
                }
            }
        }

        $absencesResult = $this->calculateAbsencesForPeriod($fromDate, $toDate);

        return response()->json([
            'processed' => count($results),
            'absences_marked' => $absencesResult['total'],
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
        
        $query = AttendanceRecord::with(['employee.attendanceDevice']);
        
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        
        $records = $query->whereBetween('date', [$fromDate, $toDate])
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
        $employeeLabel = '';
        if ($request->employee_id) {
            $employee = \App\Models\Employee::find($request->employee_id);
            if ($employee) {
                $employeeLabel = '<p><strong>الموظف:</strong> ' . $employee->name . '</p>';
            }
        }
        $html .= '<div class="header-info">' . $employeeLabel . '<p>من تاريخ: ' . $fromDate . ' | إلى تاريخ: ' . $toDate . '</p></div>';
        
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>#</th><th>اسم الموظف</th><th>رقم البصمة</th><th>التاريخ</th>';
        $html .= '<th>وقت الدخول</th><th>نوع الدخول</th><th>وقت الانصراف</th><th>نوع الانصراف</th>';
        $html .= '<th>دقائق التأخير</th><th>الخصم</th>';
        $html .= '</tr></thead><tbody>';
        
        $dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        foreach ($records as $index => $record) {
            $checkInTypeClass = $record->check_in_type == 'late' ? 'late' : ($record->check_in_type == 'early' ? 'early' : 'on-time');
            $checkInLabel = $this->getTypeLabel($record->check_in_type);
            $checkOutLabel = $this->getTypeLabel($record->check_out_type);
            
            $checkInFormatted = '-';
            if ($record->check_in_time) {
                $dt = Carbon::parse($record->check_in_time);
                $checkInFormatted = $dayNames[$dt->dayOfWeek] . ' ' . $dt->format('d/m/Y H:i:s');
            }
            $checkOutFormatted = '-';
            if ($record->check_out_time) {
                $dt = Carbon::parse($record->check_out_time);
                $checkOutFormatted = $dayNames[$dt->dayOfWeek] . ' ' . $dt->format('d/m/Y H:i:s');
            }
            
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . ($record->employee->name ?? '-') . '</td>';
            $html .= '<td>' . ($record->employee->device_user_id ?? '-') . '</td>';
            $html .= '<td>' . ($record->date) . '</td>';
            $html .= '<td>' . $checkInFormatted . '</td>';
            $html .= '<td class="' . $checkInTypeClass . '">' . $checkInLabel . '</td>';
            $html .= '<td>' . $checkOutFormatted . '</td>';
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
