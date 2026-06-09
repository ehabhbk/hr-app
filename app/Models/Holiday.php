<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = ['name', 'date', 'duration_days', 'employee_ids'];

    protected $casts = ['employee_ids' => 'array'];
}
