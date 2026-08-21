<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'salary_amount', 'purpose', 'file_path', 'status',
    ];

    protected $casts = ['salary_amount' => 'decimal:2'];

    public function employee() { return $this->belongsTo(Employee::class); }
}
