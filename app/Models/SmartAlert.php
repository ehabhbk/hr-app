<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmartAlert extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'title', 'message', 'severity', 'data', 'is_read'];

    protected $casts = ['data' => 'json', 'is_read' => 'boolean'];
}
