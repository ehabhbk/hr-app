<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'hours',
        'rate',
        'amount',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function calculateOvertime($employeeId, $date, $hours)
    {
        $settings = Setting::where('key', 'attendance')->first();
        $overtimeRate = $settings?->value['overtime_rate'] ?? 1.5;
        
        $employee = Employee::find($employeeId);
        $hourlyRate = ($employee->base_salary ?? 0) / 240;

        $amount = $hours * $hourlyRate * $overtimeRate;

        return self::create([
            'employee_id' => $employeeId,
            'date' => $date,
            'hours' => $hours,
            'rate' => $overtimeRate,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }
}
