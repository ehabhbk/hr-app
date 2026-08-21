<?php

namespace App\Http\Controllers;

use App\Models\TravelRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class TravelRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelRequest::with('employee', 'approver');
        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'destination' => 'required|string|max:255',
            'purpose' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|string',
        ]);
        $travel = TravelRequest::create($data);
        return response()->json(['message' => 'تم إنشاء طلب السفر', 'data' => $travel], 201);
    }

    public function approve(Request $request, $id)
    {
        $travel = TravelRequest::findOrFail($id);
        $travel->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        return response()->json(['message' => 'تمت الموافقة على طلب السفر', 'data' => $travel]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);
        $travel = TravelRequest::findOrFail($id);
        $travel->update(['status' => 'rejected', 'rejection_reason' => $request->rejection_reason]);
        return response()->json(['message' => 'تم رفض طلب السفر', 'data' => $travel]);
    }

    public function complete(Request $request, $id)
    {
        $travel = TravelRequest::findOrFail($id);
        $travel->update(['status' => 'completed', 'actual_cost' => $request->actual_cost]);
        return response()->json(['message' => 'تم إنهاء رحلة العمل', 'data' => $travel]);
    }

    public function destroy($id)
    {
        TravelRequest::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف طلب السفر']);
    }
}
