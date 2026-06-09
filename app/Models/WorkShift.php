<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'work_shifts';

    protected $fillable = ['name', 'start_time', 'end_time', 'is_overnight', 'week_days', 'weekend_days', 'daily_hours', 'notes', 'active'];

    protected $casts = ['week_days' => 'array', 'weekend_days' => 'array', 'active' => 'boolean', 'is_overnight' => 'boolean'];

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class, 'work_shift_id');
    }

    public function permanentAssignments()
    {
        return $this->hasMany(ShiftAssignment::class, 'work_shift_id')->whereNull('date');
    }
}
