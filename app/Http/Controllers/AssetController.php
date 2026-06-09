<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAsset;
use App\Models\Employee;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeAsset::with('employee');
        
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,movable',
            'value' => 'nullable|numeric|min:0',
            'issue_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $data['status'] = 'active';
        
        $asset = EmployeeAsset::create($data);
        
        return response()->json(['data' => $asset->load('employee'), 'message' => 'تم إضافة العهدة بنجاح'], 201);
    }

    public function show($id)
    {
        $asset = EmployeeAsset::with('employee')->findOrFail($id);
        return response()->json(['data' => $asset]);
    }

    public function update(Request $request, $id)
    {
        $asset = EmployeeAsset::findOrFail($id);
        
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:fixed,movable',
            'value' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,returned,damaged,lost',
            'return_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $asset->update($data);
        
        return response()->json(['data' => $asset->fresh('employee'), 'message' => 'تم تحديث العهدة بنجاح']);
    }

    public function destroy($id)
    {
        $asset = EmployeeAsset::findOrFail($id);
        $asset->delete();
        
        return response()->json(['message' => 'تم حذف العهدة بنجاح']);
    }

    public function returnAsset(Request $request, $id)
    {
        $asset = EmployeeAsset::findOrFail($id);
        
        $updateData = [
            'status' => 'returned',
            'return_date' => now()->toDateString(),
        ];
        
        if ($request->has('note')) {
            $updateData['notes'] = ($asset->notes ? $asset->notes . "\n" : '') . 'إرجاع: ' . $request->note;
        }
        
        $asset->update($updateData);
        
        return response()->json(['data' => $asset, 'message' => 'تم تسجيل إرجاع العهدة']);
    }
}
