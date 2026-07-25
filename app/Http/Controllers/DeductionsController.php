<?php

namespace App\Http\Controllers;

use App\Models\Deduction;
use App\Models\Employee;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class DeductionsController extends Controller
{
    use LogsActivity;

    public function index()
    {
        return response()->json(['data' => Deduction::with('employee')->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'type' => 'required|string',
            'amount' => 'required|numeric',
            'employee_id' => 'required|exists:employees,id',
            'reason' => 'nullable|string',
            'date' => 'nullable|date',
        ]);
        if (!isset($data['date'])) {
            $data['date'] = now()->toDateString();
        }
        $d = Deduction::create($data);

        $this->logActivity('deduction_created', $d, null, $data, 'خصم: ' . ($d->employee->name ?? ''), $r);

        // Admin notification
        try {
            $employee = Employee::find($data['employee_id']);
            if ($employee) {
                $whatsapp = new WhatsAppService();
                $whatsapp->notifyAdminDeduction($employee, $data['amount'], $data['reason'] ?? $data['type']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
        }

        return response()->json(['data' => $d], 201);
    }

    public function destroy($id)
    {
        $d = Deduction::findOrFail($id);
        $d->delete();

        return response()->json(null, 204);
    }
}
