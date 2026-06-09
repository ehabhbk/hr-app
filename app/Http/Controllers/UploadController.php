<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function avatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = $data['avatar']->store('avatars', 'public');
        $url = asset('storage/'.$path);

        $user = $request->user();
        if ($user) {
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                if (Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
            }

            $user->avatar = $path;
            $user->save();
        }

        return response()->json([
            'path' => $path,
            'url' => $url,
        ], 201);
    }

    public function getCv(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        if (! $employee->cv || ! Storage::disk('public')->exists($employee->cv)) {
            return response()->json(['message' => 'السيرة الذاتية غير موجودة'], 404);
        }

        $filePath = Storage::disk('public')->path($employee->cv);
        $mimeType = mime_content_type($filePath);

        $file = Storage::disk('public')->get($employee->cv);

        return response($file, 200)->header('Content-Type', $mimeType);
    }
}
