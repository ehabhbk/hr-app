<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Resources\DepartmentResource;

class DepartmentController extends Controller
{
    // عرض كل الأقسام مع الموظفين المرتبطين
    public function index()
    {
        return DepartmentResource::collection(
            Department::with('employees')->get()
        );
    }

    // عرض قسم واحد مع الموظفين المرتبطين
    public function show($id)
    {
        $department = Department::with('employees')->findOrFail($id);
        return new DepartmentResource($department);
    }

    // إضافة قسم جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
        ]);

        $department = Department::create($request->all());

        return new DepartmentResource($department);
    }

    // تحديث بيانات قسم
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $department->update($request->all());

        return new DepartmentResource($department);
    }

    // حذف قسم
    public function destroy($id)
    {
        Department::destroy($id);
        return response()->json(['message' => 'تم حذف القسم بنجاح ✅']);
    }
}