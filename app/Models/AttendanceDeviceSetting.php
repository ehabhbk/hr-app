<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDeviceSetting extends Model
{
    protected $fillable = [
        'enabled',
        'name',
        'host',
        'port',
        'driver',
        'timeout_ms',
        'sync_interval_seconds',
        'last_sync_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'timeout_ms' => 'integer',
        'sync_interval_seconds' => 'integer',
        'last_sync_at' => 'datetime',
    ];
}

