<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Face extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_device_id',
        'face_id',
        'template',
        'is_active',
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
