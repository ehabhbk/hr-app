<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use App\Http\Resources\EmployeeResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    // عرض كل الموظفين مع العلاقات المفيدة
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Employee::with(['department', 'attendanceDevice', 'compensations', 'contracts']);
        
        // Load user role to check
        $user->load('role');
        
        // Check if user is department supervisor (role.name === 'department_supervisor' with department_id)
        $isDepartmentSupervisor = $user->role && $user->role->name === 'department_supervisor' && $user->department_id;
        
        Log::info('EmployeeController index', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role_id' => $user->role_id,
            'role_name' => $user->role->name ?? null,
            'department_id' => $user->department_id,
            'is_department_supervisor' => $isDepartmentSupervisor,
        ]);
        
        // إذا كان مشرف قسم، يظهر فقط موظفي قسمه
        if ($isDepartmentSupervisor) {
            Log::info('Filtering employees by department', ['department_id' => $user->department_id]);
            $query->where('department_id', $user->department_id);
        }
        
        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('file_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by department
        if ($request->has('department_id') && $request->department_id && !$isDepartmentSupervisor) {
            $query->where('department_id', $request->department_id);
        }
        
        // Filter by status
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        $employees = $query->orderBy('name')->get();
        return EmployeeResource::collection($employees);
    }

    // عرض موظف واحد مع العلاقات
    public function show($id)
    {
        $user = auth()->user();
        $employee = Employee::with(['department', 'attendanceDevice', 'compensations', 'contracts', 'attendanceDeviceLogs', 'shiftAssignments.shift'])->findOrFail($id);
        
        // إذا كان مشرف قسم، لا يمكنه رؤية موظف من قسم آخر
        if ($user && $user->isDepartmentSupervisor() && $user->department_id != $employee->department_id) {
            return response()->json(['message' => 'غير مصرح لك برؤية هذا الموظف'], 403);
        }
        
        return new EmployeeResource($employee);
    }

    // إضافة موظف جديد
    public function store(Request $request)
    {
        $data = $request->validate([
            'file_number'    => 'nullable|string|unique:employees,file_number',
            'name'           => 'required|string',
            'email'          => 'nullable|email|unique:employees,email',
            'department_id'  => 'nullable|exists:departments,id',
            'phone'          => 'nullable|string',
            'phone_country_code' => 'nullable|string',
            'position'       => 'nullable|string',
            'position_grade' => 'nullable|string',
            'position_allowance' => 'nullable|numeric|min:0',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
            'device_user_id' => 'nullable|string|max:191',
            'hire_date'      => 'nullable|date',
            'cv'             => 'nullable|mimes:pdf,doc,docx',
            'profile_photo'  => 'nullable|image|mimes:jpg,jpeg,png',
            'address'        => 'nullable|string',
            
            // البيانات الشخصية
            'gender'         => 'nullable|in:male,female',
            'birth_date'     => 'nullable|date',
            'id_number'      => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',

            // الحضور والغيابات
            'attendance_days' => 'nullable|integer|min:0',
            'absence_days'    => 'nullable|integer|min:0',
            'late_arrivals'   => 'nullable|integer|min:0',
            'early_leaves'    => 'nullable|integer|min:0',

            // الإجازات
            'leave_count'    => 'nullable|integer|min:0',
            'leave_duration' => 'nullable|integer|min:0',
            'leave_type'     => 'nullable|in:official,sick',
            'leave_paid'     => 'nullable|boolean',

            // الرواتب والتفصيل المالي
            'base_salary'    => 'nullable|numeric|min:0',
            'position_allowance' => 'nullable|numeric|min:0',
            'advance'        => 'nullable|numeric|min:0',

            // الحالة والإنذارات
            'warnings'       => 'nullable|integer|min:0',
            'status'         => 'nullable|in:active,terminated,warning,vacation',

            'notes'          => 'nullable|string',
        ]);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        if ($request->hasFile('cv')) {
            $data['cv'] = $request->file('cv')->store('cvs', 'public');
        }

        $employee = Employee::create($data);

        // حساب وتحديث gross_salary بعد الإنشاء
        if (method_exists($employee, 'refreshGrossSalary')) {
            $employee->refreshGrossSalary();
        }

        return new EmployeeResource($employee->load(['department', 'attendanceDevice', 'compensations', 'contracts']));
    }

    // تحديث بيانات موظف
    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'file_number'    => ['nullable','string', Rule::unique('employees','file_number')->ignore($employee->id)],
            'name'           => 'nullable|string',
            'email'          => ['nullable','email', Rule::unique('employees','email')->ignore($employee->id)],
            'department_id'  => 'nullable|exists:departments,id',
            'phone'          => 'nullable|string',
            'phone_country_code' => 'nullable|string',
            'position'       => 'nullable|string',
            'position_grade' => 'nullable|string',
            'position_allowance' => 'nullable|numeric|min:0',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
            'device_user_id' => 'nullable|string|max:191',
            'hire_date'      => 'nullable|date',
            'cv'             => 'nullable|mimes:pdf,doc,docx',
            'profile_photo'  => 'nullable|image|mimes:jpg,jpeg,png',
            'address'        => 'nullable|string',
            
            // البيانات الشخصية
            'gender'         => 'nullable|in:male,female',
            'birth_date'     => 'nullable|date',
            'id_number'      => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',

            // الحضور والغيابات
            'attendance_days' => 'nullable|integer|min:0',
            'absence_days'    => 'nullable|integer|min:0',
            'late_arrivals'   => 'nullable|integer|min:0',
            'early_leaves'    => 'nullable|integer|min:0',

            // الإجازات
            'leave_count'    => 'nullable|integer|min:0',
            'leave_duration' => 'nullable|integer|min:0',
            'leave_type'     => 'nullable|in:official,sick',
            'leave_paid'     => 'nullable|boolean',

            // الرواتب والتفصيل المالي
            'base_salary'    => 'nullable|numeric|min:0',
            'position_allowance' => 'nullable|numeric|min:0',
            'advance'        => 'nullable|numeric|min:0',

            // الحالة والإنذارات
            'warnings'       => 'nullable|integer|min:0',
            'status'         => 'nullable|in:active,terminated,warning,vacation',

            'notes'          => 'nullable|string',
        ]);

        // تحديث الملفات مع حذف القديم إن وُجد
        if ($request->hasFile('profile_photo')) {
            if ($employee->profile_photo && Storage::disk('public')->exists($employee->profile_photo)) {
                Storage::disk('public')->delete($employee->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        if ($request->hasFile('cv')) {
            if ($employee->cv && Storage::disk('public')->exists($employee->cv)) {
                Storage::disk('public')->delete($employee->cv);
            }
            $data['cv'] = $request->file('cv')->store('cvs', 'public');
        }

        // منطق الإنذارات والإجازات (كما في النسخة السابقة)
        if ($request->has('status')) {
            $status = $request->input('status');

            if ($status === 'warning') {
                $employee->warnings = ($employee->warnings ?? 0) + 1;

                if ($employee->warnings >= 4) {
                    $employee->status = 'terminated';
                    // إيقاف الراتب الأساسي عند الفصل
                    $employee->base_salary = 0;
                    unset($data['status']);
                } else {
                    $employee->status = 'warning';
                    unset($data['status']);
                }
            }

            if ($status === 'vacation') {
                $employee->leave_count = ($employee->leave_count ?? 0) + 1;
                if ($request->has('leave_type')) $employee->leave_type = $request->input('leave_type');
                if ($request->has('leave_duration')) $employee->leave_duration = $request->input('leave_duration');
                if ($request->has('leave_paid')) $employee->leave_paid = $request->boolean('leave_paid');
                $employee->status = 'vacation';
                unset($data['status']);
            }
        }

        $employee->fill($data);
        $employee->save();

        // تحديث gross_salary بعد التعديل
        if (method_exists($employee, 'refreshGrossSalary')) {
            $employee->refreshGrossSalary();
        }

        return new EmployeeResource($employee->load(['department', 'attendanceDevice', 'workShift', 'compensations', 'contracts']));
    }

    // حذف موظف
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        if ($employee->profile_photo && Storage::disk('public')->exists($employee->profile_photo)) {
            Storage::disk('public')->delete($employee->profile_photo);
        }
        if ($employee->cv && Storage::disk('public')->exists($employee->cv)) {
            Storage::disk('public')->delete($employee->cv);
        }

        $employee->delete();

        return response()->json(['message' => 'تم حذف الموظف بنجاح ✅']);
    }

    /**
     * ربط رقم المستخدم على جهاز البصمة بالموظف (device_user_id)
     * POST /api/employees/{id}/assign-device-user
     * body: { device_user_id: string, attendance_device_id: optional }
     */
    public function assignDeviceUserId(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'device_user_id' => 'required|string|max:191',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
        ]);

        $employee->device_user_id = $data['device_user_id'];
        if (isset($data['attendance_device_id'])) {
            $employee->attendance_device_id = $data['attendance_device_id'];
        }
        $employee->save();

        return response()->json([
            'message' => 'تم ربط البصمة بالموظف',
            'employee' => new EmployeeResource($employee->load(['department', 'attendanceDevice'])),
        ], 200);
    }

    /**
     * جلب سجلات الحضور مع الورديات لفترة زمنية
     * GET /api/employees/{id}/attendance-with-shifts?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function attendanceWithShifts(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $from = $request->query('from');
        $to = $request->query('to');

        $result = $employee->attendanceWithShifts($from, $to);

        return response()->json($result, 200);
    }
}