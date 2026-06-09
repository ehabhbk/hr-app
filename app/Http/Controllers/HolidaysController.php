<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidaysController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Holiday::orderBy('date', 'desc')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'duration_days' => 'nullable|integer|min:1',
            'employee_ids' => 'nullable|array',
        ]);
        $h = Holiday::create($data);

        return response()->json(['data' => $h], 201);
    }

    public function destroy($id)
    {
        $h = Holiday::findOrFail($id);
        $h->delete();

        return response()->json(null, 204);
    }
}
