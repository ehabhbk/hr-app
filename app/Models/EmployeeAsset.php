<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAsset extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'description',
        'type',
        'value',
        'status',
        'issue_date',
        'return_date',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'issue_date' => 'date',
        'return_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getTypeLabelAttribute()
    {
        return [
            'fixed' => 'ثابتة',
            'movable' => 'متحركة',
        ][$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute()
    {
        return [
            'active' => 'نشط',
            'returned' => 'مرتجع',
            'damaged' => 'تالف',
            'lost' => 'فقود',
        ][$this->status] ?? $this->status;
    }

    public function getReceivedDateAttribute()
    {
        return $this->issue_date;
    }

    public function getReturnedDateAttribute()
    {
        return $this->return_date;
    }
}
