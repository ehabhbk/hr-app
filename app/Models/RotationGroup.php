<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RotationGroup extends Model
{
    protected $fillable = [
        'name',
        'shift_id',
        'start_date',
        'employee_ids',
        'active',
    ];

    protected $casts = [
        'employee_ids' => 'array',
        'start_date' => 'date',
        'active' => 'boolean',
    ];

    public function shift()
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employees', 'rotation_group_id')
            ->whereIn('id', $this->employee_ids ?? []);
    }

    /**
     * Determine which employee should work on a given date.
     * Returns the employee_id of the person working, or null.
     */
    public function getWorkingEmployeeIdForDate($date): ?int
    {
        $date = Carbon::parse($date);
        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);
        $employeeIds = $this->employee_ids;

        if (empty($employeeIds)) {
            return null;
        }

        $daysDiff = (int) $start->diffInDays($date);
        $index = $daysDiff % count($employeeIds);

        return $employeeIds[$index];
    }

    /**
     * Check if a specific employee is working on a given date.
     */
    public function isEmployeeWorking($employeeId, $date): bool
    {
        return $this->getWorkingEmployeeIdForDate($date) == $employeeId;
    }
}
