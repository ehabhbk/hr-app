<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        
        $grouped = $permissions->groupBy('module')->map(function ($group) {
            return $group->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->display_name,
                    'description' => $item->description,
                ];
            });
        });

        $modules = Permission::getModules();

        return response()->json([
            'data' => $permissions,
            'grouped' => $grouped,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'display_name' => 'required|string',
            'module' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($request->only(['name', 'display_name', 'module', 'description']));

        return response()->json([
            'message' => 'تم إنشاء الصلاحية بنجاح',
            'data' => $permission,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|unique:permissions,name,' . $id,
            'display_name' => 'sometimes|string',
            'module' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);

        $permission->update($request->only(['name', 'display_name', 'module', 'description']));

        return response()->json([
            'message' => 'تم تحديث الصلاحية بنجاح',
            'data' => $permission,
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'message' => 'تم حذف الصلاحية بنجاح',
        ]);
    }

    public function seed()
    {
        $permissions = Permission::getDefaultPermissions();
        
        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                $perm
            );
        }

        return response()->json([
            'message' => 'تم إنشاء الصلاحيات بنجاح',
            'count' => count($permissions),
        ]);
    }
}
