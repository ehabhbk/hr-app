<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingerprint extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_device_id',
        'finger_id',
        'finger_position',
        'finger',
        'template',
        'is_active',
        'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
