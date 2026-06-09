<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResignationRequest extends Model
{
    protected $table = 'resignation_requests';

    protected $fillable = [
        'employee_id',
        'resignation_date',
        'reason',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'resignation_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
