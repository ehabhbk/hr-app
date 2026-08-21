<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'latitude', 'longitude', 'radius', 'is_active'];

    protected $casts = [
        'latitude' => 'decimal:8', 'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];
}
