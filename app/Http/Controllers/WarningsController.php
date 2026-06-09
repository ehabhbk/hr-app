<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Warning;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WarningsController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Warning::with('employee')->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'reason' => 'nullable|string',
            'type' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $data['date'] = now()->format('Y-m-d');
        $data['status'] = 'active';
        
        $w = Warning::create($data);

        $employee = Employee::find($r->employee_id);
        
        if ($employee) {
            $employee->warnings = ($employee->warnings ?? 0) + 1;
            $employee->save();

            $whatsapp = new WhatsAppService();
            $whatsapp->sendWarningNotification($employee, $w);

            Notification::send(
                auth()->id(),
                'warning',
                'إنذار جديد',
                "تم إصدار إنذار لـ {$employee->name}: {$w->reason}",
                ['warning_id' => $w->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $w], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $permissions = $user->role?->permissions ?? [];
        $isAdmin = in_array('*', $permissions);
        
        if (!$isAdmin && !in_array('warnings.delete', $permissions) && !in_array('warnings.manage', $permissions)) {
            return response()->json(['error' => 'ليس لديك صلاحية حذف الإنذار'], 403);
        }
        
        $warning = Warning::findOrFail($id);
        $employeeId = $warning->employee_id;
        $warning->delete();

        $employee = Employee::find($employeeId);
        if ($employee) {
            $warningsCount = Warning::where('employee_id', $employeeId)->count();
            $employee->warnings = $warningsCount;

            if ($warningsCount === 0) {
                $employee->status = 'active';
            }
            $employee->save();
        }

        return response()->json(['message' => 'تم إلغاء الإنذار بنجاح']);
    }
}
