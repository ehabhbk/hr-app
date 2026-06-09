<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'position'        => $this->position,

            // عرض بيانات القسم بشكل مرتب
            'department'      => $this->department ? [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ] : null,

            'hire_date'       => $this->hire_date,
            
            // وردية العمل
            'work_shift_id'  => $this->work_shift_id,
            'work_shift'     => $this->workShift ? [
                'id'   => $this->workShift->id,
                'name' => $this->workShift->name,
            ] : null,

            // جهاز البصمة
            'attendance_device_id' => $this->attendance_device_id,
            'device_user_id'      => $this->device_user_id,
            'attendance_device'   => $this->attendanceDevice ? [
                'id'   => $this->attendanceDevice->id,
                'name' => $this->attendanceDevice->name,
                'host' => $this->attendanceDevice->host,
            ] : null,

            // روابط كاملة للملفات
            'cv'              => $this->cv ? asset('storage/' . $this->cv) : null,
            'profile_photo'   => $this->profile_photo ? asset('storage/' . $this->profile_photo) : null,

            'address'         => $this->address,

            // البيانات الشخصية
            'gender'          => $this->gender,
            'birth_date'      => $this->birth_date,
            'id_number'       => $this->id_number,
            'marital_status'  => $this->marital_status,

            // الحضور والانصراف والغيابات
            'attendance_days' => $this->attendance_days,
            'absence_days'    => $this->absence_days,
            'late_arrivals'   => $this->late_arrivals,
            'early_leaves'    => $this->early_leaves,

            // الإجازات
            'leave_count'     => $this->leave_count,
            'leave_duration'  => $this->leave_duration,
            'leave_type'      => $this->leave_type, // sick أو official
            'leave_paid'      => $this->leave_paid, // true أو false

            // المرتبات والحوافز والسلفيات
            'salary'          => $this->salary,
            'bonus'           => $this->bonus,
            'advance'         => $this->advance,

            // نسبة الانضباط
            'discipline_rate' => $this->discipline_rate,

            // عدد الإنذارات
            'warnings'        => $this->warnings,

            // الحالة
            'status'          => $this->status,

            'notes'           => $this->notes,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}