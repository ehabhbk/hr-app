<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'check_in_time',
        'check_in_type',
        'check_in_delay_minutes',
        'check_in_excused',
        'check_in_excuse_reason',
        'check_out_time',
        'check_out_type',
        'check_out_early_minutes',
        'check_out_excused',
        'check_out_excuse_reason',
        'worked_hours',
        'expected_hours',
        'has_delay',
        'delay_excused',
        'delay_deduction',
        'early_leave_deduction',
        'total_deduction',
        'deduction_applied',
        'warning_issued',
        'warning_id',
        'is_absent',
        'absence_excused',
        'absence_excuse_reason',
        'absence_deduction',
        'absence_days',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'check_in_delay_minutes' => 'integer',
        'check_out_early_minutes' => 'integer',
        'worked_hours' => 'decimal:2',
        'expected_hours' => 'decimal:2',
        'delay_deduction' => 'decimal:2',
        'early_leave_deduction' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'absence_deduction' => 'decimal:2',
        'absence_days' => 'decimal:2',
        'has_delay' => 'boolean',
        'delay_excused' => 'boolean',
        'check_in_excused' => 'boolean',
        'check_out_excused' => 'boolean',
        'deduction_applied' => 'boolean',
        'warning_issued' => 'boolean',
        'is_absent' => 'boolean',
        'absence_excused' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function warning(): BelongsTo
    {
        return $this->belongsTo(Warning::class, 'warning_id');
    }

    // Calculate check-in type based on shift start time
    public static function calculateCheckInType($employeeId, $checkInTime, $shiftStartTime, $allowedDelayMinutes = 5)
    {
        $shiftStart = strtotime($shiftStartTime);
        $actualStart = strtotime($checkInTime);
        
        $shiftStartMinutes = floor($shiftStart / 60);
        $actualStartMinutes = floor($actualStart / 60);
        
        // Early: before shift start time
        if ($actualStartMinutes < $shiftStartMinutes) {
            return 'early';
        }
        
        // Late: after allowed delay
        if ($actualStartMinutes > $shiftStartMinutes + $allowedDelayMinutes) {
            return 'late';
        }
        
        // On time
        return 'on_time';
    }

    // Calculate check-out type based on shift end time
    public static function calculateCheckOutType($checkOutTime, $shiftEndTime, $allowedEarlyMinutes = 5)
    {
        $shiftEnd = strtotime($shiftEndTime);
        $actualEnd = strtotime($checkOutTime);
        
        $shiftEndMinutes = floor($shiftEnd / 60);
        $actualEndMinutes = floor($actualEnd / 60);
        
        // Early: before shift end time
        if ($actualEndMinutes < $shiftEndMinutes) {
            $earlyMinutes = $shiftEndMinutes - $actualEndMinutes;
            return ['type' => 'early', 'minutes' => $earlyMinutes];
        }
        
        // Late: after shift end time
        if ($actualEndMinutes > $shiftEndMinutes) {
            return ['type' => 'late', 'minutes' => $actualEndMinutes - $shiftEndMinutes];
        }
        
        // On time
        return ['type' => 'on_time', 'minutes' => 0];
    }

    // Get type label in Arabic
    public static function getTypeLabel($type)
    {
        return match($type) {
            'early' => 'مبكر',
            'late' => 'متأخر',
            'on_time' => 'في الوقت',
            default => $type,
        };
    }
}
