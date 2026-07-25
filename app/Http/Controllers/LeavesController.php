<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class LeavesController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $leaves = Leave::with('employee')->orderBy('created_at', 'desc')->get();
        $leaves->map(fn($l) => $l->attachment_url = $l->attachment ? url('storage/' . $l->attachment) : null);
        return response()->json(['data' => $leaves]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:official,sick,maternity,hajj,unpaid',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'note' => 'nullable|string',
            'paid' => 'nullable|boolean',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $employee = Employee::find($r->employee_id);
        $days = (new \DateTime($data['from_date']))->diff(new \DateTime($data['to_date']))->days + 1;

        $leaveSettings = \App\Models\Setting::where('key', 'leaves')->first();
        $settings = $leaveSettings ? $leaveSettings->value : [
            'annual_days' => 21,
            'sick_days' => 10,
            'maternity_days' => 90,
            'hajj_days' => 14,
            'unpaid_leave_max_days' => 30,
            'notice_days' => 3,
        ];

        $leaveType = $data['type'];
        $maxDays = match($leaveType) {
            'official' => $settings['annual_days'] ?? 21,
            'sick' => $settings['sick_days'] ?? 10,
            'maternity' => $settings['maternity_days'] ?? 90,
            'hajj' => $settings['hajj_days'] ?? 14,
            'unpaid' => $settings['unpaid_leave_max_days'] ?? 30,
            default => $settings['annual_days'] ?? 21,
        };

        if ($days > $maxDays) {
            return response()->json([
                'message' => "أقصى حد مسموح لهذا النوع من الإجازة هو {$maxDays} يوم. لا يمكنك طلب {$days} أيام.",
                'error' => 'exceeds_max_days'
            ], 422);
        }

        $pendingLeave = Leave::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingLeave) {
            return response()->json([
                'message' => 'لديك طلب إجازة قيد الانتظار مسبقاً. يرجى انتظار الموافقة أو إلغاء الطلب السابق.',
                'error' => 'pending_leave_exists'
            ], 422);
        }

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
            'paid' => $leaveType === 'unpaid' ? false : $r->boolean('paid', true),
        ];

        if ($r->hasFile('attachment')) {
            $file = $r->file('attachment');
            $path = $file->store('leave-attachments', 'public');
            $leaveData['attachment'] = $path;
        }

        $leave = Leave::create(array_merge($data, $leaveData));

        $this->logActivity('leave_created', $leave, null, $data, 'طلب إجازة: ' . ($leave->employee->name ?? ''), $r);

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

        $this->logActivity('leave_status_updated', $leave, ['status' => $oldStatus], ['status' => $r->status], 'تحديث حالة إجازة: ' . $r->status, $r);

        $employee = Employee::find($leave->employee_id);
        $whatsapp = new WhatsAppService();

        if ($r->status === 'approved') {
            // Set employee status to vacation when approved
            if ($employee) {
                $employee->status = 'vacation';
                $employee->save();
                
                $whatsapp->sendLeaveNotification($employee, $leave, 'approved');
                
                // Admin notification
                try {
                    $whatsapp->notifyAdminLeave($employee, $leave, 'approved');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
                }
                
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
                
                // Admin notification
                try {
                    $whatsapp->notifyAdminLeave($employee, $leave, 'rejected');
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
                }
                
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
