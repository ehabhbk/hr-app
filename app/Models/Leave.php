<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasFactory;

    protected $table = 'leaves';

    protected $fillable = [
        'employee_id',
        'type',
        'from_date',
        'to_date',
        'days',
        'status',
        'note',
        'paid',
        'medical_certificate',
        'attachment',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'paid' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
