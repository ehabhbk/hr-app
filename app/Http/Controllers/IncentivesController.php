<?php

namespace App\Http\Controllers;

use App\Models\Incentive;
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

        return response()->json(['data' => $i], 201);
    }

    public function destroy($id)
    {
        $i = Incentive::findOrFail($id);
        $i->delete();

        return response()->json(null, 204);
    }
}
