<?php

namespace App\Http\Controllers;

use App\Models\OkrGoal;
use Illuminate\Http\Request;

class OkrGoalController extends Controller
{
    public function index(Request $request)
    {
        $query = OkrGoal::with('employee', 'department');
        if ($request->type) $query->where('type', $request->type);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->department_id) $query->where('department_id', $request->department_id);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:employee,department',
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'target_value' => 'required|numeric|min:0',
        ]);
        $goal = OkrGoal::create($data);
        return response()->json(['message' => 'تم إنشاء هدف OKR', 'data' => $goal], 201);
    }

    public function update(Request $request, $id)
    {
        $goal = OkrGoal::findOrFail($id);
        $goal->update($request->only(['title', 'description', 'current_value', 'status', 'target_value']));

        if ($goal->target_value > 0 && $goal->current_value >= $goal->target_value) {
            $goal->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'تم تحديث الهدف', 'data' => $goal]);
    }

    public function destroy($id)
    {
        OkrGoal::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف الهدف']);
    }
}
