<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Merged Employee model
 * Combines existing fields and relationships, preserves legacy names and adds new helpers.
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Fillable attributes (merged from both versions)
     */
    protected $fillable = [
        // legacy / HR fields
        'file_number',
        'employee_number',
        'name',
        'email',
        'phone',
        'phone_country_code',
        'address',
        'cv',
        'profile_photo',

        // position / job
        'position',
        'position_grade',
        'position_allowance',
        'job_type',        // alternative name used in newer model
        'grade',           // alternative name used in newer model
        'fingerprint_id',

        // department / device
        'department_id',
        'attendance_device_id',
        'attendance_device_user_id', // alternate naming
        'device_user_id',            // رقم المستخدم على جهاز البصمة (kept)

        // shift assignment
        'work_shift_id',

        // dates / contract
        'hire_date',

        // attendance / stats
        'attendance_days',
        'absence_days',
        'late_arrivals',
        'early_leaves',

        // leaves
        'leave_count',
        'leave_duration',
        'leave_type',
        'leave_paid',

        // salary / compensation
        'base_salary',
        'position_allowance',
        'advance',
        'gross_salary',
        'discipline_rate',
        'warnings',
        'status',
        'notes',

        // legacy short names kept
        'advance',

        // other
        'attendance_days',

        // insurance / bank
        'insurance_type',
        'insurance_amount',
        'bank_name',
        'bank_account',
        
        // personal info
        'gender',
        'birth_date',
        'id_number',
        'marital_status',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'decimal:2',
        'advance' => 'decimal:2',
        'position_allowance' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'discipline_rate' => 'decimal:2',
        'attendance_days' => 'integer',
        'absence_days' => 'integer',
        'late_arrivals' => 'integer',
        'early_leaves' => 'integer',
        'leave_count' => 'integer',
        'leave_duration' => 'integer',
        'leave_paid' => 'boolean',
        'warnings' => 'integer',
        'insurance_amount' => 'decimal:2',
        'birth_date' => 'date',
    ];

    /**
     * Appended accessors
     */
    protected $appends = [
        'profile_photo_url',
        'cv_url',
    ];

    /* -----------------------------------------------------------------
     | Relationships
     | -----------------------------------------------------------------
     */

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function attendanceDevice()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function workShift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    /**
     * Attendance device logs relation
     * Uses device_user_id (kept for compatibility)
     */
    public function attendanceDeviceLogs()
    {
        return $this->hasMany(AttendanceDeviceLog::class, 'device_user_id', 'device_user_id');
    }

    /**
     * Generic compensation records (advances, allowances, incentives)
     */
    public function compensations()
    {
        return $this->hasMany(Incentive::class);
    }

    public function allowances()
    {
        return $this->hasMany(Incentive::class);
    }

    public function incentives()
    {
        return $this->hasMany(Incentive::class);
    }

    public function advances()
    {
        return $this->hasMany(AdvanceRequest::class);
    }

    public function incentivesLegacy()
    {
        return $this->hasMany(Incentive::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function advancesRequests()
    {
        return $this->hasMany(AdvanceRequest::class);
    }

    public function warningsRelation()
    {
        return $this->hasMany(Warning::class);
    }

    public function warnings()
    {
        // keep compatibility with older code expecting warnings()
        return $this->hasMany(Warning::class);
    }

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function activeContract()
    {
        return $this->hasOne(EmployeeContract::class)->where('status', 'active');
    }

    /**
     * Shift assignments
     */
    public function shiftAssignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function shift_assignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function assets()
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function activeAssets()
    {
        return $this->hasMany(EmployeeAsset::class)->where('status', 'active');
    }

    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    public function fingerprints()
    {
        return $this->hasMany(Fingerprint::class);
    }

    public function activeFingerprints()
    {
        return $this->hasMany(Fingerprint::class)->where('is_active', true);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function faces()
    {
        return $this->hasMany(Face::class);
    }

    public function activeFaces()
    {
        return $this->hasMany(Face::class)->where('is_active', true);
    }

    /* -----------------------------------------------------------------
     | Accessors
     | -----------------------------------------------------------------
     */

    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo ? Storage::url($this->profile_photo) : null;
    }

    public function getCvUrlAttribute()
    {
        return $this->cv ? Storage::url($this->cv) : null;
    }

    /* -----------------------------------------------------------------
     | Salary calculations
     | -----------------------------------------------------------------
     */

    /**
     * Calculate gross salary dynamically:
     * base_salary + position_allowance + sum(allowances) + sum(incentives) - sum(advances)
     */
    public function calculateGrossSalary(): float
    {
        $base = (float) ($this->base_salary ?? 0);
        $positionAllowance = (float) ($this->position_allowance ?? 0);

        // If EmployeeCompensation exists, use it; otherwise fallback to Incentive/Advance models
        $allowances = 0.0;
        $incentives = 0.0;
        $advances = 0.0;

        if (method_exists($this, 'allowances')) {
            $allowances = (float) $this->allowances()->sum('amount');
        } elseif (class_exists(Incentive::class)) {
            // no-op: keep 0
        }

        if (method_exists($this, 'incentives')) {
            $incentives = (float) $this->incentives()->sum('amount');
        } elseif (class_exists(Incentive::class)) {
            $incentives = (float) $this->incentivesLegacy()->sum('value');
        }

        if (method_exists($this, 'advances')) {
            $advances = (float) $this->advances()->where('active', true)->sum('amount');
        } elseif (class_exists(AdvanceRequest::class)) {
            $advances = (float) $this->advancesRequests()->where('status', 'approved')->sum('amount');
        }

        $gross = $base + $positionAllowance + $allowances + $incentives - $advances;

        return round($gross, 2);
    }

    /**
     * Refresh and persist gross_salary
     */
    public function refreshGrossSalary(): void
    {
        $this->gross_salary = $this->calculateGrossSalary();
        $this->save();
    }

    /* -----------------------------------------------------------------
     | Attendance helpers
     | -----------------------------------------------------------------
     */

    /**
     * Fetch attendance logs combined with shift assignments between two dates.
     *
     * @param  string|Carbon|null  $from
     * @param  string|Carbon|null  $to
     */
    public function attendanceWithShifts($from = null, $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        // If no device_user_id, return empty structure
        $deviceUserId = $this->device_user_id ?? $this->attendance_device_user_id ?? null;
        if (empty($deviceUserId)) {
            return [
                'employee_id' => $this->id,
                'device_user_id' => null,
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'days' => [],
            ];
        }

        // Use AttendanceDeviceLog model if exists
        $logsQuery = AttendanceDeviceLog::where('device_user_id', $deviceUserId)
            ->whereBetween('timestamp', [$fromDate, $toDate])
            ->orderBy('timestamp');

        $logs = $logsQuery->get();

        // Shift assignments keyed by date
        $assignments = $this->shiftAssignments()
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->with('shift')
            ->get()
            ->keyBy('date');

        // Group logs by date
        $grouped = $logs->groupBy(function ($item) {
            return Carbon::parse($item->timestamp)->toDateString();
        });

        $result = [];
        foreach ($grouped as $date => $dayLogs) {
            $shiftAssignment = $assignments->get($date);
            $result[] = [
                'date' => $date,
                'shift_assignment' => $shiftAssignment ? [
                    'id' => $shiftAssignment->id,
                    'work_shift_id' => $shiftAssignment->work_shift_id,
                    'shift' => $shiftAssignment->shift,
                ] : null,
                'logs' => $dayLogs->values(),
            ];
        }

        // Add assignments without logs
        foreach ($assignments as $date => $assignment) {
            if (! isset($grouped[$date])) {
                $result[] = [
                    'date' => $date,
                    'shift_assignment' => [
                        'id' => $assignment->id,
                        'work_shift_id' => $assignment->work_shift_id,
                        'shift' => $assignment->shift,
                    ],
                    'logs' => collect(),
                ];
            }
        }

        // Sort ascending by date
        usort($result, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return [
            'employee_id' => $this->id,
            'device_user_id' => $deviceUserId,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'days' => $result,
        ];
    }

    /* -----------------------------------------------------------------
     | Model events
     | -----------------------------------------------------------------
     */

    protected static function booted()
    {
        static::deleting(function (Employee $employee) {
            // delete files if exist
            if ($employee->profile_photo && Storage::disk('public')->exists($employee->profile_photo)) {
                Storage::disk('public')->delete($employee->profile_photo);
            }
            if ($employee->cv && Storage::disk('public')->exists($employee->cv)) {
                Storage::disk('public')->delete($employee->cv);
            }
        });
    }
}
