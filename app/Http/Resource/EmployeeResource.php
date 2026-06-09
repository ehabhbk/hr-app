<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'position'        => $this->position,
            'hire_date'       => $this->hire_date,
            'cv'              => $this->cv,
            'profile_photo'   => $this->profile_photo,
            'address'         => $this->address,
            'attendance_days' => $this->attendance_days,
            'absence_days'    => $this->absence_days,
            'late_arrivals'   => $this->late_arrivals,
            'early_leaves'    => $this->early_leaves,
            'official_leaves' => $this->official_leaves,
            'sick_leaves'     => $this->sick_leaves,
            'salary'          => $this->salary,
            'bonus'           => $this->bonus,
            'advance'         => $this->advance,
            'discipline_rate' => $this->discipline_rate,
            'status'          => $this->status,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,

            // ✅ الحقول الخاصة بالإنذارات والإجازات
            'warnings'        => $this->warnings,
            'leave_count'     => $this->leave_count,
            'leave_type'      => $this->leave_type,
            'leave_duration'  => $this->leave_duration,
            'leave_paid'      => $this->leave_paid,

            // عرض القسم المرتبط بالموظف
            'department' => new DepartmentResource(
                $this->whenLoaded('department')
            ),
        ];
    }
}