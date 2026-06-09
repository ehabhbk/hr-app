<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'driver',
        'enabled',
        'device_id',
        'password',
        'serial_number',
        'supports_face',
        'supports_fingerprint',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'supports_face' => 'boolean',
        'supports_fingerprint' => 'boolean',
    ];
}
