<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Resources\DepartmentResource;

class DepartmentController extends Controller
{
    use LogsActivity;

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

        $this->logActivity('department_created', $department, null, $request->all(), 'إضافة قسم: ' . ($request->input('name') ?? ''), $request);

        return new DepartmentResource($department);
    }

    // تحديث بيانات قسم
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $old = $department->toArray();
        $department->update($request->all());
        $this->logActivity('department_updated', $department, $old, $request->all(), 'تعديل قسم: ' . $department->name, $request);

        return new DepartmentResource($department);
    }

    // حذف قسم
    public function destroy($id)
    {
        $dept = Department::findOrFail($id);
        $old = $dept->toArray();
        $dept->delete();
        $this->logActivity('department_deleted', null, $old, null, 'حذف قسم: ' . ($old['name'] ?? ''), request());
        return response()->json(['message' => 'تم حذف القسم بنجاح ✅']);
    }
}