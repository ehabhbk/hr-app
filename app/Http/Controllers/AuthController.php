<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    // تسجيل مستخدم جديد
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'avatar'   => 'nullable|string',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'avatar'   => $request->avatar,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Get permissions
        $permissions = [];
        if ($user->role) {
            $perms = $user->role->permissions;
            if (is_array($perms)) {
                $permissions = $perms;
            }
        }
        if (in_array('*', $permissions)) {
            $permissions = ['*'];
        }

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
            'permissions' => $permissions,
        ], 201);
    }

    // تسجيل الدخول بالـ username
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة ❌'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Get permissions from role directly
        $permissions = [];
        $role = Role::find($user->role_id);
        if ($role) {
            $perms = $role->permissions;
            if (is_array($perms)) {
                $permissions = $perms;
            }
        }
        if (in_array('*', $permissions)) {
            $permissions = ['*'];
        }

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
            'permissions' => $permissions,
        ]);
    }

    // تسجيل الخروج
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح ✅']);
    }
}