<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return new UserResource($request->user()->load(['role', 'department']));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::with(['role', 'department']);
        
        // Check if user is department_supervisor - show only their department's employees
        if ($user->role && $user->role->name === 'department_supervisor' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }
        
        // Search by name or username
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }
        
        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }
        
        // Filter by role
        if ($request->has('role_id') && $request->role_id) {
            $query->where('role_id', $request->role_id);
        }
        
        return UserResource::collection($query->orderBy('id', 'desc')->get());
    }

    public function show($id)
    {
        $user = User::with(['role', 'department'])->findOrFail($id);
        return new UserResource($user);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'full_name' => 'nullable|string|max:255',
            'avatar' => 'nullable|string',
            'role_id' => 'nullable|exists:roles,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::query()->create($data);

        return (new UserResource($user->load(['role', 'department'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:6',
            'full_name' => 'nullable|string|max:255',
            'avatar' => 'sometimes|nullable|string',
            'role_id' => 'sometimes|nullable|exists:roles,id',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $user->fill($data);
        $user->save();

        return new UserResource($user->load(['role', 'department']));
    }

    public function destroy($id)
    {
        $user = User::query()->findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح ✅']);
    }
}

