<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'destination', 'purpose', 'from_date', 'to_date',
        'estimated_cost', 'actual_cost', 'status', 'attachment', 'notes',
        'rejection_reason', 'approved_by',
    ];

    protected $casts = [
        'from_date' => 'date', 'to_date' => 'date',
        'estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
