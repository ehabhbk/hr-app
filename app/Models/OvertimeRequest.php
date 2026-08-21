<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'date', 'hours', 'reason', 'status',
        'amount', 'rate', 'approved_by', 'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date', 'hours' => 'decimal:2',
        'amount' => 'decimal:2', 'rate' => 'decimal:2',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
