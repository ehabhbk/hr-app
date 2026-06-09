<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incentive extends Model
{
    use HasFactory;

    protected $table = 'incentives';

    protected $fillable = ['type', 'value', 'employee_id', 'note', 'date', 'is_recurring'];

    protected $casts = [
        'is_recurring' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
