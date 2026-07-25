<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with('creator');

        if ($request->user()->hasRole && !in_array('*', $request->user()->role->permissions ?? [])) {
            $query->where(function ($q) use ($request) {
                $q->where('target', 'all')
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('target', 'department')
                         ->whereJsonContains('target_ids', $request->user()->department_id);
                  })
                  ->orWhere(function ($q2) use ($request) {
                      $q2->where('target', 'specific')
                         ->whereJsonContains('target_ids', $request->user()->id);
                  });
            });
        }

        if (!$request->boolean('show_all')) {
            $now = now();
            $query->where('is_active', true)
                  ->where(function ($q) use ($now) {
                      $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now);
                  })
                  ->where(function ($q) use ($now) {
                      $q->whereNull('expire_at')->orWhere('expire_at', '>', $now);
                  });
        }

        $announcements = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($announcements);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'target' => 'sometimes|string|in:all,department,specific',
            'target_ids' => 'nullable|array',
            'publish_at' => 'nullable|date',
            'expire_at' => 'nullable|date|after:publish_at',
        ]);

        $data['created_by'] = $request->user()->id;

        $announcement = Announcement::create($data);

        $this->logActivity('announcement_created', $announcement, null, $data, 'إنشاء إعلان: ' . $data['title'], $request);

        return response()->json(['data' => $announcement, 'message' => 'تم نشر الإعلان بنجاح'], 201);
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $old = $announcement->toArray();

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'target' => 'sometimes|string|in:all,department,specific',
            'target_ids' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'publish_at' => 'nullable|date',
            'expire_at' => 'nullable|date',
        ]);

        $announcement->update($data);

        $this->logActivity('announcement_updated', $announcement, $old, $data, 'تعديل إعلان: ' . ($data['title'] ?? $announcement->title), $request);

        return response()->json(['data' => $announcement, 'message' => 'تم تعديل الإعلان']);
    }

    public function destroy(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $old = $announcement->toArray();

        $announcement->delete();

        $this->logActivity('announcement_deleted', null, $old, null, 'حذف إعلان: ' . $old['title'], $request);

        return response()->json(['message' => 'تم حذف الإعلان']);
    }
}
