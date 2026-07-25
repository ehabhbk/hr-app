<?php

namespace App\Http\Controllers;

use App\Models\Offboarding;
use App\Models\Employee;
use Illuminate\Http\Request;

class OffboardingController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Offboarding::with('employee', 'handler')->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:termination,resignation,retirement',
            'last_working_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $data['handled_by'] = $request->user()->id;
        $data['checklist'] = [
            'تسوية مالية' => false,
            'إرجاع العهد والأدوات' => false,
            'إلغاء الوصول للنظام' => false,
            'مقابلة خروج' => false,
            'تسليم بطاقة العمل' => false,
        ];

        $offboarding = Offboarding::create($data);
        $employee = Employee::find($data['employee_id']);
        $this->logActivity('offboarding_created', $offboarding, null, $data, 'بدء سير خروج: ' . ($employee->name ?? ''), $request);

        return response()->json(['data' => $offboarding, 'message' => 'تم إنشاء سير الخروج'], 201);
    }

    public function update(Request $request, $id)
    {
        $offboarding = Offboarding::findOrFail($id);
        $old = $offboarding->toArray();

        $data = $request->validate([
            'settlement_done' => 'sometimes|boolean',
            'assets_returned' => 'sometimes|boolean',
            'access_revoked' => 'sometimes|boolean',
            'exit_interview_done' => 'sometimes|boolean',
            'exit_interview_notes' => 'nullable|string',
            'status' => 'sometimes|in:in_progress,completed',
            'checklist' => 'nullable|array',
        ]);

        $offboarding->update($data);

        if ($offboarding->settlement_done && $offboarding->assets_returned && $offboarding->access_revoked && $offboarding->exit_interview_done) {
            $offboarding->update(['status' => 'completed']);
        }

        $this->logActivity('offboarding_updated', $offboarding, $old, $data, 'تحديث سير خروج', $request);

        return response()->json(['data' => $offboarding, 'message' => 'تم التحديث']);
    }

    public function destroy(Request $request, $id)
    {
        $offboarding = Offboarding::findOrFail($id);
        $old = $offboarding->toArray();
        $offboarding->delete();
        $this->logActivity('offboarding_deleted', null, $old, null, 'حذف سير خروج', $request);
        return response()->json(['message' => 'تم الحذف']);
    }
}
