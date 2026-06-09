<?php

namespace App\Http\Controllers;

use App\Models\WorkShift;
use App\Models\ShiftAssignment;
use App\Models\Employee;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index()
    {
        return response()->json(['data' => WorkShift::withCount('permanentAssignments')->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string',
            'start_time' => 'nullable|date_format:H:i,H:i:s',
            'end_time' => 'nullable|date_format:H:i,H:i:s',
            'is_overnight' => 'nullable|boolean',
            'week_days' => 'nullable',
            'weekend_days' => 'nullable',
            'daily_hours' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
            'color' => 'nullable|string',
        ]);
        
        if (isset($data['week_days']) && is_array($data['week_days'])) {
            $data['week_days'] = json_encode($data['week_days']);
        }
        if (isset($data['weekend_days']) && is_array($data['weekend_days'])) {
            $data['weekend_days'] = json_encode($data['weekend_days']);
        }
        
        $data['active'] = $data['active'] ?? true;
        $data['is_overnight'] = $data['is_overnight'] ?? false;
        
        $shift = WorkShift::create($data);

        return response()->json(['data' => $shift, 'message' => 'تم إنشاء الوردية بنجاح'], 201);
    }

    public function update(Request $r, $id)
    {
        $shift = WorkShift::findOrFail($id);
        
        $data = $r->validate([
            'name' => 'required|string',
            'start_time' => 'nullable|date_format:H:i,H:i:s',
            'end_time' => 'nullable|date_format:H:i,H:i:s',
            'is_overnight' => 'nullable|boolean',
            'week_days' => 'nullable',
            'weekend_days' => 'nullable',
            'daily_hours' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
            'color' => 'nullable|string',
        ]);
        
        if (isset($data['week_days']) && is_array($data['week_days'])) {
            $data['week_days'] = json_encode($data['week_days']);
        }
        if (isset($data['weekend_days']) && is_array($data['weekend_days'])) {
            $data['weekend_days'] = json_encode($data['weekend_days']);
        }
        
        $data['is_overnight'] = $data['is_overnight'] ?? false;
        $shift->update($data);

        return response()->json(['data' => $shift, 'message' => 'تم تحديث الوردية بنجاح']);
    }

    public function destroy($id)
    {
        $shift = WorkShift::findOrFail($id);
        
        ShiftAssignment::where('work_shift_id', $id)->delete();
        
        Employee::where('work_shift_id', $id)->update(['work_shift_id' => null]);
        
        $shift->delete();

        return response()->json(['message' => 'تم حذف الوردية وتبعاتها بنجاح'], 200);
    }
}
