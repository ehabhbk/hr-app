<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('user_id', auth()->id());
        $status = $request->input('status', 'unread');

        $query = Notification::where('user_id', $userId)
            ->orWhere('recipient_id', $userId);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'total' => $notifications->total(),
                'unread_count' => Notification::where('user_id', $userId)
                    ->where('status', 'unread')
                    ->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ]
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'تم علامة كمقروء']);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'تم علامة الكل كمقروء']);
    }

    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('status', 'unread')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'type' => 'required',
            'title' => 'required',
            'message' => 'required',
        ]);

        $notification = Notification::send(
            $request->user_id,
            $request->type,
            $request->title,
            $request->message,
            $request->data ?? []
        );

        return response()->json(['data' => $notification]);
    }
}
