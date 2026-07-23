<?php

namespace App\Http\Controllers;

use App\Models\ResignationRequest;
use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\Request;

class ResignationRequestController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => ResignationRequest::with('employee')->orderBy('created_at', 'desc')->get()
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'resignation_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $pending = ResignationRequest::where('employee_id', $data['employee_id'])
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            return response()->json([
                'message' => 'يوجد طلب استقالة قيد الانتظار لهذا الموظف بالفعل',
                'error' => 'pending_exists'
            ], 422);
        }

        $request = ResignationRequest::create(array_merge($data, ['status' => 'pending']));

        $employee = Employee::find($data['employee_id']);
        if ($employee) {
            Notification::send(
                auth()->id(),
                'resignation',
                'طلب استقالة جديد',
                "تم تقديم طلب استقالة من {$employee->name}",
                ['resignation_id' => $request->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $request], 201);
    }

    public function updateStatus(Request $r, $id)
    {
        $request = ResignationRequest::findOrFail($id);
        $r->validate(['status' => 'required|in:pending,approved,rejected']);

        $oldStatus = $request->status;
        $request->status = $r->status;
        $request->admin_notes = $r->input('admin_notes');
        $request->save();

        $employee = Employee::find($request->employee_id);
        if ($r->status === 'approved' && $employee) {
            $employee->status = 'inactive';
            $employee->save();

            // Admin notification
            try {
                $whatsapp = new \App\Services\WhatsAppService();
                $whatsapp->notifyAdminResignation($employee, 'approved');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
            }

            Notification::send(
                auth()->id(),
                'resignation',
                'تمت الموافقة على الاستقالة',
                "تمت الموافقة على استقالة {$employee->name}",
                ['resignation_id' => $request->id, 'employee_id' => $employee->id]
            );
        } elseif ($r->status === 'rejected' && $employee) {
            // Admin notification
            try {
                $whatsapp = new \App\Services\WhatsAppService();
                $whatsapp->notifyAdminResignation($employee, 'rejected');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
            }

            Notification::send(
                auth()->id(),
                'resignation',
                'تم رفض الاستقالة',
                "تم رفض استقالة {$employee->name}",
                ['resignation_id' => $request->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $request]);
    }
}
