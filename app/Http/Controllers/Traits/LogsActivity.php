<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

trait LogsActivity
{
    protected function logActivity($action, $subject = null, $oldValues = null, $newValues = null, $description = null, Request $request = null)
    {
        $user = $request ? $request->user() : auth()->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'username' => $user?->username ?? $user?->full_name ?? 'system',
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
