<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class LeavesController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Leave::with('employee')->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'note' => 'nullable|string',
            'paid' => 'nullable|boolean',
            'medical_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
        ]);

        $employee = Employee::find($r->employee_id);
        $days = (new \DateTime($data['from_date']))->diff(new \DateTime($data['to_date']))->days + 1;

        // Get leave settings
        $leaveSettings = \App\Models\Setting::where('key', 'leaves')->first();
        $settings = $leaveSettings ? $leaveSettings->value : [
            'annual_days' => 21,
            'sick_days' => 10,
            'maternity_days' => 90,
            'notice_days' => 3,
        ];

        // Validate based on leave type
        $leaveType = $data['type'];
        $maxDays = match($leaveType) {
            'official' => $settings['annual_days'] ?? 21,
            'sick' => $settings['sick_days'] ?? 10,
            'maternity' => $settings['maternity_days'] ?? 90,
            default => $settings['annual_days'] ?? 21,
        };

        // Check if employee has enough leave days remaining
        $usedDays = Leave::where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected')
            ->whereYear('created_at', now()->year)
            ->sum('days');

        $remainingDays = $maxDays - $usedDays;

        if ($days > $remainingDays && $leaveType !== 'sick') {
            return response()->json([
                'message' => "لا يمكنك طلب {$days} أيام. يتبقى لديك {$remainingDays} يوم إجازة فقط من أصل {$maxDays} يوم",
                'error' => 'insufficient_leave_days'
            ], 422);
        }

        // Check if employee already has a pending leave request
        $pendingLeave = Leave::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingLeave) {
            return response()->json([
                'message' => 'لديك طلب إجازة قيد الانتظار مسبقاً. يرجى انتظار الموافقة أو إلغاء الطلب السابق.',
                'error' => 'pending_leave_exists'
            ], 422);
        }

        // Check notice days for official leave
        if ($leaveType === 'official') {
            $noticeDays = $settings['notice_days'] ?? 3;
            $fromDate = new \DateTime($data['from_date']);
            $today = new \DateTime();
            $daysUntilLeave = $today->diff($fromDate)->days;

            if ($daysUntilLeave < $noticeDays) {
                return response()->json([
                    'message' => "يجب تقديم طلب الإجازة قبل {$noticeDays} أيام على الأقل من تاريخ البدء",
                    'error' => 'insufficient_notice'
                ], 422);
            }
        }

        $leaveData = [
            'days' => $days,
            'status' => 'pending',
            'paid' => $r->boolean('paid', true),
        ];

        if ($r->hasFile('medical_certificate')) {
            $file = $r->file('medical_certificate');
            $path = $file->store('medical_certificates', 'public');
            $leaveData['medical_certificate'] = $path;
        }

        $leave = Leave::create(array_merge($data, $leaveData));

        if ($employee) {
            Notification::send(
                auth()->id(),
                'leave',
                'طلب إجازة جديد',
                "تم تقديم طلب إجازة من {$employee->name}",
                ['leave_id' => $leave->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $leave], 201);
    }

    public function updateStatus(Request $r, $id)
    {
        $leave = Leave::findOrFail($id);
        $r->validate(['status' => 'required|in:pending,approved,rejected']);

        $oldStatus = $leave->status;
        $leave->status = $r->status;
        $leave->save();

        $employee = Employee::find($leave->employee_id);
        $whatsapp = new WhatsAppService();

        if ($r->status === 'approved') {
            // Set employee status to vacation when approved
            if ($employee) {
                $employee->status = 'vacation';
                $employee->save();
                
                $whatsapp->sendLeaveNotification($employee, $leave, 'approved');
                
                Notification::send(
                    auth()->id(),
                    'leave',
                    'تمت الموافقة على الإجازة',
                    "تمت الموافقة على إجازة {$employee->name}",
                    ['leave_id' => $leave->id, 'employee_id' => $employee->id]
                );
            }
        } elseif ($r->status === 'rejected') {
            // Check if employee has other approved leaves and update status
            $this->updateEmployeeLeaveStatus($employee);
            
            if ($employee) {
                $whatsapp->sendLeaveNotification($employee, $leave, 'rejected');
                
                Notification::send(
                    auth()->id(),
                    'leave',
                    'تم رفض الإجازة',
                    "تم رفض إجازة {$employee->name}",
                    ['leave_id' => $leave->id, 'employee_id' => $employee->id]
                );
            }
        }

        return response()->json(['data' => $leave]);
    }

    public function checkExpiredLeaves()
    {
        $expiredLeaves = Leave::where('status', 'approved')
            ->where('to_date', '<', now()->toDateString())
            ->get();

        foreach ($expiredLeaves as $leave) {
            $employee = Employee::find($leave->employee_id);
            $this->updateEmployeeLeaveStatus($employee);
        }

        return response()->json(['message' => 'تم تحديث حالات الإجازات المنتهية', 'count' => $expiredLeaves->count()]);
    }

    private function updateEmployeeLeaveStatus($employee)
    {
        if (!$employee) return;
        
        // Check if employee has any current approved leave
        $hasActiveLeave = Leave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('from_date', '<=', now()->toDateString())
            ->where('to_date', '>=', now()->toDateString())
            ->exists();

        if (!$hasActiveLeave) {
            $employee->status = 'active';
            $employee->save();
        }
    }
}
