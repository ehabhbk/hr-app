<?php

namespace App\Http\Controllers;

use App\Models\GpsCheckin;
use App\Models\Employee;
use Illuminate\Http\Request;

class GpsCheckinController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:check_in,check_out',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $data['timestamp'] = now();
        $checkin = GpsCheckin::create($data);
        return response()->json(['data' => $checkin, 'message' => 'تم تسجيل ' . ($data['type'] === 'check_in' ? 'الحضور' : 'الانصراف')], 201);
    }

    public function index(Request $request)
    {
        $query = GpsCheckin::with('employee')->latest('timestamp');
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('date')) $query->whereDate('timestamp', $request->date);
        return response()->json(['data' => $query->paginate($request->input('per_page', 50))]);
    }
}
