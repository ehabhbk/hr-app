<?php

namespace App\Http\Controllers;

use App\Models\Training;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Training::with('employee')->latest();
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('certificate_expiry')
                  ->where('certificate_expiry', '<=', now()->addDays(30))
                  ->where('certificate_expiry', '>=', now());
        }
        return response()->json(['data' => $query->paginate($request->input('per_page', 25))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'course_name' => 'required|string',
            'institution' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'certificate_expiry' => 'nullable|date',
            'certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notes' => 'nullable|string',
        ]);

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $request->file('certificate_file')->store('training-certificates', 'public');
        }

        if ($data['end_date'] && !$data['certificate_expiry']) $data['status'] = 'completed';
        elseif ($data['certificate_expiry'] && \Carbon\Carbon::parse($data['certificate_expiry'])->isPast()) $data['status'] = 'expired';

        $training = Training::create($data);
        $employee = \App\Models\Employee::find($data['employee_id']);
        $this->logActivity('training_created', $training, null, $data, 'إضافة دورة: ' . $data['course_name'] . ' - ' . ($employee->name ?? ''), $request);

        return response()->json(['data' => $training, 'message' => 'تم إضافة الدورة'], 201);
    }

    public function update(Request $request, $id)
    {
        $training = Training::findOrFail($id);
        $old = $training->toArray();
        $data = $request->validate([
            'course_name' => 'sometimes|string',
            'institution' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'certificate_expiry' => 'nullable|date',
            'certificate_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:ongoing,completed,expired',
        ]);

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $request->file('certificate_file')->store('training-certificates', 'public');
        }

        $training->update($data);
        $this->logActivity('training_updated', $training, $old, $data, 'تحديث دورة: ' . $training->course_name, $request);

        return response()->json(['data' => $training, 'message' => 'تم التحديث']);
    }

    public function destroy(Request $request, $id)
    {
        $training = Training::findOrFail($id);
        $old = $training->toArray();
        $training->delete();
        $this->logActivity('training_deleted', null, $old, null, 'حذف دورة: ' . $old['course_name'], $request);
        return response()->json(['message' => 'تم الحذف']);
    }
}
