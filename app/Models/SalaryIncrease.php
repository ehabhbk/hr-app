<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryIncrease extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'old_salary',
        'new_salary',
        'increase_amount',
        'increase_percent',
        'reason',
        'effective_date',
        'approved_by',
        'status',
    ];

    protected $casts = [
        'old_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'increase_amount' => 'decimal:2',
        'increase_percent' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->increase_amount = $model->new_salary - $model->old_salary;
            $model->increase_percent = $model->old_salary > 0 
                ? (($model->new_salary - $model->old_salary) / $model->old_salary) * 100 
                : 0;
        });
    }
}
