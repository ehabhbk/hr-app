<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\WorkShift;
use App\Models\Warning;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckAbsences extends Command
{
    protected $signature = 'attendance:check-absences {date?}';
    protected $description = 'Check for employee absences for a given date';

    public function handle()
    {
        $date = $this->argument('date') ?? Carbon::yesterday()->format('Y-m-d');
        $tz = 'Africa/Khartoum';
        
        $this->info("Checking absences for date: $date");
        
        // Get attendance settings
        $settings = Setting::where('key', 'attendance')->first();
        $attendanceSettings = $settings ? $settings->value : [];
        
        $absenceAfterMinutes = $attendanceSettings['absence_after_minutes'] ?? 60;
        $absenceDeductionDays = $attendanceSettings['absence_deduction_days'] ?? 1;
        
        // Get all active employees
        $employees = Employee::where('status', 'active')->get();
        
        $absentCount = 0;
        
        foreach ($employees as $employee) {
            // Get employee's shift
            $shift = $employee->work_shift_id 
                ? WorkShift::find($employee->work_shift_id) 
                : null;
            
            $shiftStart = $shift ? $shift->start_time : '08:00';
            
            // Calculate absence threshold time
            $shiftStartTime = Carbon::parse($date . ' ' . $shiftStart, $tz);
            $absenceThreshold = $shiftStartTime->copy()->addMinutes($absenceAfterMinutes);
            
            // Check if current time is past the threshold
            $now = Carbon::now($tz);
            if ($now->lt($absenceThreshold)) {
                continue; // Not past threshold yet
            }
            
            // Check if employee has any attendance record for this date
            $record = AttendanceRecord::where('employee_id', $employee->id)
                ->where('date', $date)
                ->first();
            
            // If no record exists, mark as absent
            if (!$record) {
                $record = AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'is_absent' => true,
                    'absence_days' => $absenceDeductionDays,
                ]);
                
                // Calculate absence deduction
                $baseSalary = $employee->base_salary ?? 0;
                $positionAllowance = $employee->position_allowance ?? 0;
                $transportAllowance = $employee->transport_allowance ?? 0;
                $housingAllowance = $employee->housing_allowance ?? 0;
                $foodAllowance = $employee->food_allowance ?? 0;
                $grossSalary = $baseSalary + $positionAllowance + $transportAllowance + $housingAllowance + $foodAllowance;
                
                $dailyRate = $grossSalary / 30;
                $record->absence_deduction = round($dailyRate * $absenceDeductionDays, 2);
                $record->total_deduction = $record->absence_deduction;
                $record->save();
                
                $absentCount++;
                $this->line("Marked {$employee->name} as absent - deduction: {$record->absence_deduction}");
            }
        }
        
        $this->info("Done. Marked $absentCount employees as absent.");
    }
}
