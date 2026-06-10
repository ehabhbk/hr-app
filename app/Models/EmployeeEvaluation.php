<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEvaluation extends Model
{
    protected $fillable = [
        'employee_id',
        'appearance',
        'behavior',
        'performance',
        'total_score',
        'period',
        'notes',
        'evaluated_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
