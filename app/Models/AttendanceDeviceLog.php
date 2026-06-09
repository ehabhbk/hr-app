<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDeviceLog extends Model
{
    protected $fillable = [
        'uid',
        'device_user_id',
        'state',
        'timestamp',
        'raw',
        'device_id',
    ];

    protected $casts = [
        'uid' => 'integer',
        'state' => 'integer',
        'timestamp' => 'datetime',
        'raw' => 'array',
    ];
}

