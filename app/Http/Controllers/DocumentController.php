<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with('employee');
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->type) $query->where('type', $request->type);

        if ($request->expiring) {
            $query->where('expiry_date', '<=', now()->addDays(30))
                  ->where('expiry_date', '>=', now());
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'file_path' => 'nullable|string',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $doc = Document::create($data);
        return response()->json(['message' => 'تم رفع الوثيقة', 'data' => $doc], 201);
    }

    public function update(Request $request, $id)
    {
        $doc = Document::findOrFail($id);
        $doc->update($request->only(['title', 'type', 'issue_date', 'expiry_date', 'notes']));
        return response()->json(['message' => 'تم تحديث الوثيقة', 'data' => $doc]);
    }

    public function destroy($id)
    {
        $doc = Document::findOrFail($id);
        if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->delete();
        return response()->json(['message' => 'تم حذف الوثيقة']);
    }
}
