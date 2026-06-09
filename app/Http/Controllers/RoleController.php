<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return RoleResource::collection($roles);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return new RoleResource($role);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        // Set display_name from name_ar or fallback to name
        $data['display_name'] = $data['name_ar'] ?? $request->input('display_name', $data['name']);

        $role = Role::create($data);

        if (!empty($request->permissions)) {
            $perms = is_array($request->permissions) ? $request->permissions : [$request->permissions];
            if (in_array('*', $perms)) {
                $role->update(['permissions' => ['*']]);
            } else {
                $role->update(['permissions' => $perms]);
            }
        }

        return new RoleResource($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = [];
        
        if ($request->has('name_ar')) {
            $data['name_ar'] = $request->name_ar;
        } elseif ($request->has('display_name')) {
            $data['name_ar'] = $request->display_name;
        }
        if ($request->has('description')) {
            $data['description'] = $request->description;
        }
        if ($request->has('color')) {
            $data['color'] = $request->color;
        }
        
        $role->update($data);

        if ($request->has('permissions')) {
            $perms = is_array($request->permissions) ? $request->permissions : [$request->permissions];
            
            if (empty($perms) || in_array('*', $perms)) {
                $role->update(['permissions' => ['*']]);
            } else {
                $role->update(['permissions' => $perms]);
            }
        }

        return new RoleResource($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        
        if ($role->name === 'admin' || $role->name === 'employee') {
            return response()->json(['error' => 'Cannot delete default roles'], 400);
        }

        $role->permissions()->delete();
        $role->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
