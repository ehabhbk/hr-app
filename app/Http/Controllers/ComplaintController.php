<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = Complaint::with('employee', 'assignee');
        if ($request->type) $query->where('type', $request->type);
        if ($request->status) $query->where('status', $request->status);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:complaint,suggestion',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'attachment' => 'nullable|string',
        ]);
        $complaint = Complaint::create($data);
        return response()->json(['message' => 'تم إرسال الشكوى/الاقتراح', 'data' => $complaint], 201);
    }

    public function update(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);
        $complaint->update($request->only(['status', 'response', 'assigned_to']));
        return response()->json(['message' => 'تم تحديث الشكوى', 'data' => $complaint]);
    }

    public function destroy($id)
    {
        Complaint::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف الشكوى']);
    }
}
