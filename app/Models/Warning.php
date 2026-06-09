<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    use HasFactory;

    protected $table = 'warnings';

    protected $fillable = ['employee_id', 'type', 'reason', 'note', 'date', 'status'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
