<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use App\Models\Employee;

class MeController extends Controller
{
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // Load role fresh from DB
            $role = Role::where('id', $user->role_id)->first();
            
            // Get permissions - handle both JSON string and array
            $permissions = [];
            if ($role) {
                $rolePerms = $role->permissions;
                // If it's a string (JSON), decode it
                if (is_string($rolePerms)) {
                    $decoded = json_decode($rolePerms, true);
                    if (is_array($decoded)) {
                        $permissions = $decoded;
                    }
                } elseif (is_array($rolePerms)) {
                    $permissions = $rolePerms;
                }
                Log::info('MeController - role perms debug', [
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'role_perms_raw' => $rolePerms,
                    'decoded_perms' => $permissions,
                    'is_admin' => in_array('*', $permissions)
                ]);
            }
            
            // If user has '*', they have all permissions
            if (in_array('*', $permissions)) {
                $permissions = ['*'];
            }
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->username ?? $user->full_name ?? 'User',
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                ],
                'permissions' => $permissions,
                'is_admin' => in_array('*', $permissions),
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->name_ar ?? $role->name,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('MeController error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $employee = Employee::find($user->employee_id);
        
        if (!$employee) {
            return response()->json(['message' => 'لا يوجد موظف مرتبط'], 404);
        }

        $data = $request->validate([
            'phone' => 'nullable|string',
            'phone_country_code' => 'nullable|string',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
        ]);

        $employee->update($data);
        return response()->json(['data' => $employee, 'message' => 'تم تحديث الملف الشخصي']);
    }
}
