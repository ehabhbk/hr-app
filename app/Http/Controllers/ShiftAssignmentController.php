<?php

namespace App\Http\Controllers;

use App\Models\ShiftAssignment;
use App\Models\Employee;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function index(Request $r)
    {
        $q = ShiftAssignment::with(['employee', 'shift']);
        
        if ($r->has('permanent') && $r->permanent) {
            $q->whereNull('date');
        } else {
            if ($r->from) {
                $q->where('date', '>=', $r->from);
            }
            if ($r->to) {
                $q->where('date', '<=', $r->to);
            }
        }
        
        if ($r->employee_id) {
            $q->where('employee_id', $r->employee_id);
        }

        return response()->json(['data' => $q->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_shift_id' => 'required|exists:work_shifts,id',
        ]);
        
        // Update employee's work_shift_id
        $employee = Employee::find($data['employee_id']);
        $employee->work_shift_id = $data['work_shift_id'];
        $employee->save();
        
        $existing = ShiftAssignment::where('employee_id', $data['employee_id'])
            ->whereNull('date')
            ->first();
            
        if ($existing) {
            $existing->work_shift_id = $data['work_shift_id'];
            $existing->save();
            return response()->json(['data' => $existing]);
        }
        
        $assignment = ShiftAssignment::create([
            'employee_id' => $data['employee_id'],
            'work_shift_id' => $data['work_shift_id'],
            'date' => null,
        ]);

        return response()->json(['data' => $assignment], 201);
    }

    public function destroy($id)
    {
        $a = ShiftAssignment::findOrFail($id);
        
        // Clear employee's work_shift_id if this is the active assignment
        if (!$a->date) {
            $employee = Employee::find($a->employee_id);
            if ($employee && $employee->work_shift_id == $a->work_shift_id) {
                $employee->work_shift_id = null;
                $employee->save();
            }
        }
        
        $a->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
    
    public function byEmployee($employeeId)
    {
        $assignment = ShiftAssignment::where('employee_id', $employeeId)
            ->whereNull('date')
            ->with('shift')
            ->first();
            
        return response()->json(['data' => $assignment]);
    }
}
