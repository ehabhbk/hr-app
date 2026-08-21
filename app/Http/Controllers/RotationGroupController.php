<?php

namespace App\Http\Controllers;

use App\Models\RotationGroup;
use App\Models\Employee;
use Illuminate\Http\Request;

class RotationGroupController extends Controller
{
    public function index()
    {
        $groups = RotationGroup::with('shift')->get();
        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'shift_id' => 'required|exists:work_shifts,id',
            'start_date' => 'required|date',
            'employee_ids' => 'required|array|min:2',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $group = RotationGroup::create($data);

        Employee::whereIn('id', $data['employee_ids'])->update([
            'rotation_group_id' => $group->id,
        ]);

        return response()->json($group->load('shift'), 201);
    }

    public function update(Request $request, $id)
    {
        $group = RotationGroup::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string',
            'shift_id' => 'sometimes|exists:work_shifts,id',
            'start_date' => 'sometimes|date',
            'employee_ids' => 'sometimes|array|min:2',
            'employee_ids.*' => 'exists:employees,id',
            'active' => 'sometimes|boolean',
        ]);

        $oldEmployeeIds = $group->employee_ids ?? [];

        $group->update($data);

        $newEmployeeIds = $data['employee_ids'] ?? $oldEmployeeIds;

        // Remove old assignments
        Employee::where('rotation_group_id', $group->id)->update([
            'rotation_group_id' => null,
        ]);

        // Assign new employees
        Employee::whereIn('id', $newEmployeeIds)->update([
            'rotation_group_id' => $group->id,
        ]);

        return response()->json($group->load('shift'));
    }

    public function destroy($id)
    {
        $group = RotationGroup::findOrFail($id);

        Employee::where('rotation_group_id', $group->id)->update([
            'rotation_group_id' => null,
        ]);

        $group->delete();

        return response()->json(['message' => 'تم حذف مجموعة التناوب بنجاح']);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => 'required|array|min:2',
            'start_date' => 'required|date',
            'days' => 'nullable|integer|min:1|max:60',
        ]);

        $employeeIds = $data['employee_ids'];
        $startDate = $data['start_date'];
        $days = $data['days'] ?? 14;

        $employees = Employee::whereIn('id', $employeeIds)->pluck('name', 'id');

        $schedule = [];
        for ($i = 0; $i < $days; $i++) {
            $date = \Carbon\Carbon::parse($startDate)->addDays($i);
            $index = $i % count($employeeIds);
            $empId = $employeeIds[$index];
            $schedule[] = [
                'date' => $date->toDateString(),
                'day' => $date->translatedFormat('l'),
                'employee_id' => $empId,
                'employee_name' => $employees[$empId] ?? '-',
            ];
        }

        return response()->json($schedule);
    }
}
