<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdpPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'title', 'description', 'skill_area',
        'start_date', 'target_date', 'status', 'progress', 'notes',
    ];

    protected $casts = ['start_date' => 'date', 'target_date' => 'date', 'progress' => 'integer'];

    public function employee() { return $this->belongsTo(Employee::class); }
}
