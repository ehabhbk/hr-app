<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftAssignment extends Model
{
    use HasFactory;

    protected $table = 'shift_assignments';

    protected $fillable = ['employee_id', 'work_shift_id', 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }
}
