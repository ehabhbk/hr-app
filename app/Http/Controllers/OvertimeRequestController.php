<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = OvertimeRequest::with('employee', 'approver');
        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0.5|max:12',
            'reason' => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $settings = Setting::where('key', 'attendance')->first();
        $rate = $settings?->value['overtime_rate'] ?? 1.5;

        $hourlyRate = ($employee->base_salary ?? 0) / 240;
        $data['amount'] = round($data['hours'] * $hourlyRate * $rate, 2);
        $data['rate'] = $rate;

        $overtime = OvertimeRequest::create($data);
        return response()->json(['message' => 'تم إنشاء طلب أوفرتايم', 'data' => $overtime], 201);
    }

    public function approve(Request $request, $id)
    {
        $overtime = OvertimeRequest::findOrFail($id);
        $overtime->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        return response()->json(['message' => 'تمت الموافقة على طلب الأوفرتايم', 'data' => $overtime]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);
        $overtime = OvertimeRequest::findOrFail($id);
        $overtime->update(['status' => 'rejected', 'rejection_reason' => $request->rejection_reason]);
        return response()->json(['message' => 'تم رفض طلب الأوفرتايم', 'data' => $overtime]);
    }

    public function destroy($id)
    {
        OvertimeRequest::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف طلب الأوفرتايم']);
    }
}
