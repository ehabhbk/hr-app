<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_id',
        'type',
        'title',
        'message',
        'data',
        'status',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public static function send($userId, $type, $title, $message, $data = [], $recipientId = null)
    {
        return self::create([
            'user_id' => $userId,
            'recipient_id' => $recipientId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'unread',
        ]);
    }

    public static function sendToRole($roleName, $type, $title, $message, $data = [])
    {
        $users = User::whereHas('role', function($q) use ($roleName) {
            $q->where('name', $roleName);
        })->get();

        foreach ($users as $user) {
            self::send($user->id, $type, $title, $message, $data);
        }
    }
}
