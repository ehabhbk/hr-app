<?php

namespace App\Http\Controllers;

use App\Models\Incentive;
use App\Models\Employee;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class IncentivesController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Incentive::with('employee')->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'type' => 'required|string',
            'value' => 'required|numeric',
            'employee_id' => 'required|exists:employees,id',
            'note' => 'nullable|string',
            'date' => 'nullable|date',
        ]);
        if (!isset($data['date'])) {
            $data['date'] = now()->toDateString();
        }
        // Button incentives from Employee.tsx are always one-time (not recurring)
        $data['is_recurring'] = false;
        $i = Incentive::create($data);

        // Admin notification
        try {
            $employee = Employee::find($data['employee_id']);
            if ($employee) {
                $whatsapp = new WhatsAppService();
                $whatsapp->notifyAdminIncentive($employee, $data['value'], $data['note'] ?? $data['type']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
        }

        return response()->json(['data' => $i], 201);
    }

    public function destroy($id)
    {
        $i = Incentive::findOrFail($id);
        $i->delete();

        return response()->json(null, 204);
    }
}
