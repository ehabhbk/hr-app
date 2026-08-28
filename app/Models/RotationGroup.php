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
        'rotation_days',
        'employee_ids',
        'active',
    ];

    protected $casts = [
        'employee_ids' => 'array',
        'start_date' => 'date',
        'rotation_days' => 'integer',
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

        // Number of consecutive days each employee works before rotating.
        // e.g. rotation_days = 1 -> day-by-day, 2 -> two days per employee, ...
        $period = max(1, (int) $this->rotation_days);
        $index = (int) floor($daysDiff / $period) % count($employeeIds);

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
