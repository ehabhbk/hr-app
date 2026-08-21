<?php

namespace App\Http\Controllers;

use App\Models\SalaryCertificate;
use App\Models\Employee;
use Illuminate\Http\Request;

class SalaryCertificateController extends Controller
{
    public function index(Request $request)
    {
        $query = SalaryCertificate::with('employee');
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'purpose' => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $data['salary_amount'] = $employee->base_salary ?? 0;

        $cert = SalaryCertificate::create($data);
        return response()->json(['message' => 'تم إنشاء شهادة الراتب', 'data' => $cert], 201);
    }

    public function update(Request $request, $id)
    {
        $cert = SalaryCertificate::findOrFail($id);
        $cert->update(['status' => 'issued']);
        return response()->json(['message' => 'تم إصدار شهادة الراتب', 'data' => $cert]);
    }

    public function destroy($id)
    {
        SalaryCertificate::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف شهادة الراتب']);
    }
}
