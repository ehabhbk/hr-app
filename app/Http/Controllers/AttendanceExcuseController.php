<?php

namespace App\Http\Controllers;

use App\Models\AttendanceExcuse;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class AttendanceExcuseController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = AttendanceExcuse::with('employee', 'reviewer')->latest();
        
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        
        if ($request->user()->hasRole && !in_array('*', $request->user()->role->permissions ?? [])) {
            $query->where('employee_id', $request->user()->employee_id ?? 0);
        }
        
        return response()->json(['data' => $query->paginate($request->input('per_page', 25))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'type' => 'required|in:late,absence,early_leave',
            'reason' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('excuse-attachments', 'public');
        }

        $excuse = AttendanceExcuse::create($data);
        $employee = Employee::find($data['employee_id']);
        $this->logActivity('excuse_created', $excuse, null, $data, 'طلب عذر حضور: ' . ($employee->name ?? ''), $request);

        return response()->json(['data' => $excuse, 'message' => 'تم إرسال طلب العذر بنجاح'], 201);
    }

    public function review(Request $request, $id)
    {
        $excuse = AttendanceExcuse::findOrFail($id);
        $old = ['status' => $excuse->status];

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string',
        ]);

        $excuse->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
        ]);

        $this->logActivity('excuse_reviewed', $excuse, $old, $data, 'مراجعة عذر حضور: ' . $data['status'], $request);

        return response()->json(['data' => $excuse, 'message' => 'تمت مراجعة العذر']);
    }
}
