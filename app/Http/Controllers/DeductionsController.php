<?php

namespace App\Http\Controllers;

use App\Models\Deduction;
use Illuminate\Http\Request;

class DeductionsController extends Controller
{
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

        return response()->json(['data' => $d], 201);
    }

    public function destroy($id)
    {
        $d = Deduction::findOrFail($id);
        $d->delete();

        return response()->json(null, 204);
    }
}
