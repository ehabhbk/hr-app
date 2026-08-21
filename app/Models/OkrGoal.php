<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OkrGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'department_id', 'title', 'description', 'type',
        'period_start', 'period_end', 'target_value', 'current_value', 'status',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date',
        'target_value' => 'decimal:2', 'current_value' => 'decimal:2',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function department() { return $this->belongsTo(Department::class); }

    public function getProgressPercentAttribute(): float
    {
        return $this->target_value > 0
            ? round(($this->current_value / $this->target_value) * 100, 1)
            : 0;
    }
}
