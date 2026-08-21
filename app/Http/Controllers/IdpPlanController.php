<?php

namespace App\Http\Controllers;

use App\Models\IdpPlan;
use Illuminate\Http\Request;

class IdpPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = IdpPlan::with('employee');
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->status) $query->where('status', $request->status);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'skill_area' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'target_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);
        $plan = IdpPlan::create($data);
        return response()->json(['message' => 'تم إنشاء خطة التطوير', 'data' => $plan], 201);
    }

    public function update(Request $request, $id)
    {
        $plan = IdpPlan::findOrFail($id);
        $plan->update($request->only(['title', 'description', 'skill_area', 'start_date', 'target_date', 'status', 'progress', 'notes']));
        return response()->json(['message' => 'تم تحديث خطة التطوير', 'data' => $plan]);
    }

    public function destroy($id)
    {
        IdpPlan::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف خطة التطوير']);
    }
}
