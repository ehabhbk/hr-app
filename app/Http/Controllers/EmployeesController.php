<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Fingerprint;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;

class EmployeesController extends Controller
{
    /**
     * تحويل وضع الإصبع إلى رقم على الجهاز
     */
    private function fingerPositionToId($position, $finger)
    {
        $rightFingers = ['thumb' => 0, 'index' => 1, 'middle' => 2, 'ring' => 3, 'pinky' => 4];
        $leftFingers = ['thumb' => 5, 'index' => 6, 'middle' => 7, 'ring' => 8, 'pinky' => 9];
        if ($position === 'right') {
            return $rightFingers[$finger] ?? 0;
        }
        return $leftFingers[$finger] ?? 5;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('role');
            
            $query = Employee::with('department', 'workShift', 'incentives');
            
            // Check if user has a department-related role (supervisor or manager of department)
            $roleName = strtolower($user->role->name_ar ?? $user->role->name ?? '');
            $isDepartmentRelated = (
                (str_contains($roleName, 'مشرف') && str_contains($roleName, 'قسم')) ||
                (str_contains($roleName, 'manager') && str_contains($roleName, 'department')) ||
                (str_contains($roleName, 'supervisor') && str_contains($roleName, 'department')) ||
                str_contains($roleName, 'department_supervisor') ||
                str_contains($roleName, 'department_manager')
            );
            
            Log::info('EmployeesController - User check', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role_name' => $user->role->name ?? null,
                'role_name_ar' => $user->role->name_ar ?? null,
                'department_id' => $user->department_id,
                'is_department_related' => $isDepartmentRelated,
            ]);
            
            // If department-related user, filter by their department
            if ($isDepartmentRelated && $user->department_id) {
                Log::info('Filtering by department', ['department_id' => $user->department_id]);
                $query->where('department_id', $user->department_id);
            }
            
            $employees = $query->orderBy('name')->get();
            Log::info('Total employees returned', ['count' => $employees->count()]);

            return response()->json([
                'data' => $employees->map(function ($emp) {
                    $baseSalary = (float) ($emp->base_salary ?? 0);
                    $positionAllowance = (float) ($emp->position_allowance ?? 0);
                    // Only recurring allowances (is_recurring = true), excluding one-time button incentives
                    $recurringTotal = $emp->incentives ? $emp->incentives->filter(function($i) {
                        return $i->is_recurring ?? str_starts_with($i->type, 'allowance_');
                    })->sum('value') : 0;
                    $totalSalary = $baseSalary + $positionAllowance + $recurringTotal;

                    return [
                        'id' => $emp->id,
                        'file_number' => $emp->file_number,
                        'name' => $emp->name,
                        'email' => $emp->email,
                        'phone' => $emp->phone,
                        'position' => $emp->position,
                        'position_grade' => $emp->position_grade,
                        'position_allowance' => $emp->position_allowance,
                        'department_id' => $emp->department_id,
                        'department' => $emp->department ? [
                            'id' => $emp->department->id,
                            'name' => $emp->department->name,
                        ] : null,
                        'attendance_device_id' => $emp->attendance_device_id,
                        'device_user_id' => $emp->device_user_id,
                        'work_shift_id' => $emp->work_shift_id,
                        'work_shift' => $emp->workShift ? [
                            'id' => $emp->workShift->id,
                            'name' => $emp->workShift->name,
                        ] : null,
                        'hire_date' => $emp->hire_date,
                        'base_salary' => $emp->base_salary,
                        'address' => $emp->address,
                        'notes' => $emp->notes,
                        'status' => $emp->status ?? 'active',
                        'warnings_count' => $emp->warnings()->count() ?? 0,
                        'leave_count' => $emp->leave_count ?? 0,
                        'profile_photo' => $emp->profile_photo,
                        'profile_photo_url' => $emp->profile_photo_url,
                        'cv' => $emp->cv,
                        'cv_url' => $emp->cv_url,
                        'salary' => $emp->base_salary,
                        'total_salary' => $totalSalary,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'file_number' => 'required|string',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'phone_country_code' => 'nullable|string',
            'position' => 'nullable|string',
            'position_grade' => 'nullable|string',
            'position_allowance' => 'nullable|numeric',
            'department_id' => 'nullable|exists:departments,id',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
            'device_user_id' => 'nullable|string|unique:employees,device_user_id',
            'hire_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,terminated,warning,vacation',
            'profile_photo' => 'nullable|file|image',
            'cv' => 'nullable|file|mimes:pdf,doc,docx',
            'insurance_type' => 'nullable|string|in:none,health,social,both',
            'insurance_amount' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            
            // البيانات الشخصية
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'id_number' => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
        ]);

        // التحقق من عدم تكرار رقم البصمة
        if (!empty($data['device_user_id'])) {
            $existingEmployee = Employee::where('device_user_id', $data['device_user_id'])->first();
            if ($existingEmployee) {
                return response()->json([
                    'message' => 'رقم البصمة ' . $data['device_user_id'] . ' مستخدم بالفعل للموظف: ' . $existingEmployee->name,
                    'error' => 'duplicate_device_user_id',
                ], 422);
            }
        }

        // Parse JSON strings for allowances, incentives, fingerprints, assets
        $allowances = [];
        if ($r->has('allowances')) {
            $allowancesInput = $r->input('allowances');
            if (is_string($allowancesInput)) {
                $allowances = json_decode($allowancesInput, true) ?? [];
            } else {
                $allowances = $allowancesInput ?? [];
            }
        }

        $incentivesData = [];
        if ($r->has('incentives')) {
            $incentivesInput = $r->input('incentives');
            if (is_string($incentivesInput)) {
                $incentivesData = json_decode($incentivesInput, true) ?? [];
            } else {
                $incentivesData = $incentivesInput ?? [];
            }
        }

        $assets = [];
        if ($r->has('assets')) {
            $assetsInput = $r->input('assets');
            if (is_string($assetsInput)) {
                $assets = json_decode($assetsInput, true) ?? [];
            } else {
                $assets = $assetsInput ?? [];
            }
        }

        $fingerprints = [];
        if ($r->has('fingerprints')) {
            $fingerprintsInput = $r->input('fingerprints');
            if (is_string($fingerprintsInput)) {
                $fingerprints = json_decode($fingerprintsInput, true) ?? [];
            } else {
                $fingerprints = $fingerprintsInput ?? [];
            }
        }

        // Handle file uploads
        if ($r->hasFile('profile_photo')) {
            $path = $r->file('profile_photo')->store('photos', 'public');
            $data['profile_photo'] = $path;
        }

        if ($r->hasFile('cv')) {
            $path = $r->file('cv')->store('cvs', 'public');
            $data['cv'] = $path;
        }

        $employee = Employee::create($data);

        // Auto-register employee on device if requested (user + fingerprints)
        if ($r->boolean('register_on_device') && $employee->device_user_id && $employee->attendance_device_id) {
            try {
                $device = \App\Models\AttendanceDevice::find($employee->attendance_device_id);
                if ($device && $device->enabled) {
                    $zk = new ZKTeco($device->host, (int)($device->port ?? 4370));
                    if ($zk->connect()) {
                        $zk->disableDevice();
                        $zk->setUser(
                            (int)$employee->device_user_id,
                            $employee->device_user_id,
                            $employee->name,
                            $employee->phone ?? ''
                        );
                        // Upload fingerprint templates if provided in request
                        if (!empty($fingerprints)) {
                            foreach ($fingerprints as $fp) {
                                $templateData = $fp['template'] ?? null;
                                if ($templateData) {
                                    $fingerId = $this->fingerPositionToId($fp['finger_position'] ?? 'right', $fp['finger'] ?? 'thumb');
                                    try {
                                        $zk->enrollFingerprint((int)$employee->device_user_id, $fingerId, base64_decode($templateData));
                                    } catch (\Exception $e) {
                                        Log::warning('FP auto-upload failed: '.$e->getMessage());
                                    }
                                }
                            }
                        }
                        $zk->enableDevice();
                        $zk->disconnect();
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Auto-register employee on device failed: '.$e->getMessage());
            }
        }

        // Save fingerprints to database
        foreach ($fingerprints as $fp) {
            $employee->fingerprints()->create([
                'finger_id' => $fp['finger_id'] ?? $this->fingerPositionToId($fp['finger_position'] ?? 'right', $fp['finger'] ?? 'thumb'),
                'finger_position' => $fp['finger_position'] ?? 'right',
                'finger' => $fp['finger'] ?? 'thumb',
                'template' => $fp['template'] ?? null,
                'attendance_device_id' => $employee->attendance_device_id,
                'is_active' => true,
            ]);
        }

        // Create allowances in incentives table
        foreach ($allowances as $allowance) {
            $type = $allowance['type'] ?? 'other';
            $note = '';
            
            // Handle custom allowance name
            if ($type === 'custom' && !empty($allowance['custom_name'])) {
                $note = $allowance['custom_name'];
                $type = 'custom';
            } else {
                $note = $type;
            }
            
            $employee->incentives()->create([
                'type' => 'allowance_' . $type,
                'value' => $allowance['value'] ?? 0,
                'note' => $note,
            ]);
        }

        // Create incentives in incentives table
        foreach ($incentivesData as $incentive) {
            $type = $incentive['type'] ?? 'bonus';
            $note = '';
            
            // Handle custom incentive name
            if ($type === 'custom' && !empty($incentive['custom_name'])) {
                $note = $incentive['custom_name'];
            }
            
            $employee->incentives()->create([
                'type' => $type,
                'value' => $incentive['value'] ?? 0,
                'note' => $note,
            ]);
        }

        // Create employee assets
        foreach ($assets as $assetData) {
            $employee->assets()->create([
                'name' => $assetData['name'] ?? '',
                'description' => $assetData['description'] ?? '',
                'type' => $assetData['type'] ?? 'fixed',
                'value' => $assetData['value'] ?? 0,
                'status' => 'active',
            ]);
        }

        // Send WhatsApp notification for new employee appointment
        try {
            $whatsappSettings = \App\Models\Setting::where('key', 'whatsapp')->first();
            if ($whatsappSettings && ($whatsappSettings->value['enabled'] ?? false) && ($whatsappSettings->value['notify_on_appointment'] ?? true)) {
                $orgSettings = \App\Models\Setting::where('key', 'organization')->first();
                $orgName = $orgSettings->value['name'] ?? 'المؤسسة';
                
                $departmentName = '';
                if ($employee->department_id) {
                    $dept = \App\Models\Department::find($employee->department_id);
                    $departmentName = $dept ? $dept->name : '';
                }

                $template = $whatsappSettings->value['message_template_appointment'] ?? 
                    'عزيزي {name}، يسعدنا إخباركم بانضمامكم إلينا في {company} بتاريخ {date}. الوظيفة: {position}، القسم: {department}، الراتب: {salary}';

                $message = str_replace([
                    '{name}',
                    '{company}',
                    '{date}',
                    '{position}',
                    '{department}',
                    '{salary}'
                ], [
                    $employee->name,
                    $orgName,
                    $employee->hire_date,
                    $employee->position ?? '',
                    $departmentName,
                    number_format($employee->base_salary ?? 0) . ' SDG'
                ], $template);

                if ($employee->phone) {
                    $whatsapp = new \App\Services\WhatsAppService();
                    $whatsapp->sendMessage($employee->phone, $message);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WhatsApp notification error for new employee: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $employee,
            'message' => 'تم إضافة الموظف بنجاح',
        ], 201);
    }

    public function show($id)
    {
        $employee = Employee::with('department', 'attendanceDevice', 'leaves', 'warningsRelation', 'assets', 'incentives')->findOrFail($id);

        // Calculate total salary from database
        $baseSalary = (float) ($employee->base_salary ?? 0);
        $positionAllowance = (float) ($employee->position_allowance ?? 0);
        
        // Get allowances and incentives from incentives table
        $incentives = $employee->incentives ?? collect();
        // Split by is_recurring (fallback to type prefix for legacy data)
        $allowancesList = $incentives->filter(function($i) {
            return $i->is_recurring ?? str_starts_with($i->type, 'allowance_');
        })->values()->toArray();
        $incentivesList = $incentives->filter(function($i) {
            return !($i->is_recurring ?? str_starts_with($i->type, 'allowance_'));
        })->values()->toArray();
        
        $allowancesTotal = $incentives->filter(function($i) {
            return $i->is_recurring ?? str_starts_with($i->type, 'allowance_');
        })->sum('value');
        $incentivesTotal = $incentives->filter(function($i) {
            return !($i->is_recurring ?? str_starts_with($i->type, 'allowance_'));
        })->sum('value');
        
        $insuranceAmount = (float) ($employee->insurance_amount ?? 0);
        // total_salary = base + position + recurring allowances only (NOT one-time incentives)
        $totalSalary = $baseSalary + $positionAllowance + $allowancesTotal - $insuranceAmount;

        // Get active leave (only APPROVED and not expired)
        $activeLeave = null;
        $now = now();
        $today = $now->toDateString();
        $leaves = $employee->leaves ?? [];
        
        foreach ($leaves as $leave) {
            if ($leave->status === 'approved' && $leave->to_date->format('Y-m-d') >= $today && $leave->from_date->format('Y-m-d') <= $today) {
                $activeLeave = $leave;
                break;
            }
        }

        $remainingDays = 0;
        if ($activeLeave) {
            $toDate = new \DateTime($activeLeave->to_date->format('Y-m-d'));
            $fromDate = new \DateTime($today);
            $remainingDays = max(0, $toDate->diff($fromDate)->days);
        }

        // Get warnings count from relationship
        $warnings = $employee->warningsRelation()->get() ?? [];
        $warningsCount = count($warnings);

        return response()->json([
            'data' => [
                'id' => $employee->id,
                'file_number' => $employee->file_number,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'phone_country_code' => $employee->phone_country_code,
                'gender' => $employee->gender,
                'birth_date' => $employee->birth_date,
                'id_number' => $employee->id_number ?? $employee->national_id,
                'national_id' => $employee->national_id,
                'marital_status' => $employee->marital_status,
                'position' => $employee->position,
                'position_grade' => $employee->position_grade,
                'position_allowance' => $employee->position_allowance,
                'department_id' => $employee->department_id,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'name' => $employee->department->name,
                ] : null,
                'work_shift_id' => $employee->work_shift_id,
                'attendance_device_id' => $employee->attendance_device_id,
                'attendance_device' => $employee->attendanceDevice ? [
                    'id' => $employee->attendanceDevice->id,
                    'name' => $employee->attendanceDevice->name,
                    'ip' => $employee->attendanceDevice->ip,
                    'port' => $employee->attendanceDevice->port,
                ] : null,
                'device_user_id' => $employee->device_user_id,
                'hire_date' => $employee->hire_date,
                'base_salary' => $employee->base_salary,
                'address' => $employee->address,
                'notes' => $employee->notes,
                'status' => $employee->status ?? 'active',
                'profile_photo' => $employee->profile_photo,
                'profile_photo_url' => $employee->profile_photo_url,
                'cv' => $employee->cv,
                'cv_url' => $employee->cv_url,
                'salary' => $employee->base_salary,
                'total_salary' => $totalSalary,
                'allowances' => $allowancesList,
                'allowances_total' => $allowancesTotal,
                'incentives' => $incentivesList,
                'incentives_total' => $incentivesTotal,
                'insurance_type' => $employee->insurance_type ?? 'none',
                'insurance_amount' => $employee->insurance_amount ?? 0,
                'bank_name' => $employee->bank_name ?? '',
                'bank_account' => $employee->bank_account ?? '',
                'assets' => $employee->assets->map(function($asset) {
                    return [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'description' => $asset->description,
                        'type' => $asset->type,
                        'value' => $asset->value,
                        'status' => $asset->status,
                        'asset_number' => $asset->asset_number ?? null,
                        'serial_number' => $asset->serial_number ?? null,
                        'returned_date' => $asset->return_date ?? null,
                    ];
                }),
                'warnings' => $warnings,
                'warnings_count' => $warningsCount,
                'leave_count' => $employee->leave_count ?? 0,
                'active_leave' => $activeLeave ? [
                    'type' => $activeLeave->type,
                    'from_date' => $activeLeave->from_date,
                    'to_date' => $activeLeave->to_date,
                    'days' => $activeLeave->days,
                    'remaining_days' => $remainingDays,
                    'status' => $activeLeave->status,
                    'paid' => $activeLeave->paid,
                    'medical_certificate' => $activeLeave->medical_certificate,
                ] : null,
            ],
        ]);
    }

    public function update(Request $r, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $r->validate([
            'file_number' => 'nullable|string',
            'name' => 'sometimes|required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'phone_country_code' => 'nullable|string',
            'position' => 'nullable|string',
            'position_grade' => 'nullable|string',
            'position_allowance' => 'nullable|numeric',
            'department_id' => 'nullable|exists:departments,id',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
            'device_user_id' => 'nullable|string',
            'hire_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,terminated,warning,vacation',
            'insurance_type' => 'nullable|string|in:none,health,social,both',
            'insurance_amount' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account' => 'nullable|string',
            
            // البيانات الشخصية
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'id_number' => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
        ]);

        // التحقق من عدم تكرار رقم البصمة عند التعديل
        if (!empty($data['device_user_id'])) {
            $existingEmployee = Employee::where('device_user_id', $data['device_user_id'])
                ->where('id', '!=', $id)
                ->first();
            if ($existingEmployee) {
                return response()->json([
                    'message' => 'رقم البصمة ' . $data['device_user_id'] . ' مستخدم بالفعل للموظف: ' . $existingEmployee->name,
                    'error' => 'duplicate_device_user_id',
                ], 422);
            }
        }

        // Handle JSON strings for allowances, incentives, and assets
        $allowances = [];
        if ($r->has('allowances')) {
            $allowancesInput = $r->input('allowances');
            if (is_string($allowancesInput)) {
                $allowances = json_decode($allowancesInput, true) ?? [];
            } else {
                $allowances = $allowancesInput ?? [];
            }
        }

        $incentivesData = [];
        if ($r->has('incentives')) {
            $incentivesInput = $r->input('incentives');
            if (is_string($incentivesInput)) {
                $incentivesData = json_decode($incentivesInput, true) ?? [];
            } else {
                $incentivesData = $incentivesInput ?? [];
            }
        }

        $assets = [];
        if ($r->has('assets')) {
            $assetsInput = $r->input('assets');
            if (is_string($assetsInput)) {
                $assets = json_decode($assetsInput, true) ?? [];
            } else {
                $assets = $assetsInput ?? [];
            }
        }

        $fingerprints = [];
        if ($r->has('fingerprints')) {
            $fingerprintsInput = $r->input('fingerprints');
            if (is_string($fingerprintsInput)) {
                $fingerprints = json_decode($fingerprintsInput, true) ?? [];
            } else {
                $fingerprints = $fingerprintsInput ?? [];
            }
        }

        // Remove arrays from data before updating employee
        unset($data['allowances'], $data['incentives'], $data['assets'], $data['fingerprints']);
        
        $employee->update($data);

        // Auto-register employee on device if requested (update case with fingerprints)
        if ($r->boolean('register_on_device') && $employee->device_user_id && $employee->attendance_device_id) {
            try {
                $device = \App\Models\AttendanceDevice::find($employee->attendance_device_id);
                if ($device && $device->enabled) {
                    $zk = new ZKTeco($device->host, (int)($device->port ?? 4370));
                    if ($zk->connect()) {
                        $zk->disableDevice();
                        $zk->setUser(
                            (int)$employee->device_user_id,
                            $employee->device_user_id,
                            $employee->name,
                            $employee->phone ?? ''
                        );
                        // Upload fingerprint templates from request if provided
                        if (!empty($fingerprints)) {
                            foreach ($fingerprints as $fp) {
                                $templateData = $fp['template'] ?? null;
                                if ($templateData) {
                                    $fingerId = $this->fingerPositionToId($fp['finger_position'] ?? 'right', $fp['finger'] ?? 'thumb');
                                    try {
                                        $zk->enrollFingerprint((int)$employee->device_user_id, $fingerId, base64_decode($templateData));
                                    } catch (\Exception $e) {
                                        Log::warning('FP auto-upload on update failed: '.$e->getMessage());
                                    }
                                }
                            }
                        }
                        $zk->enableDevice();
                        $zk->disconnect();
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Auto-register employee on device failed: '.$e->getMessage());
            }
        }

        // Update allowances
        if (!empty($allowances)) {
            // Delete old allowances
            $employee->incentives()->where('type', 'like', 'allowance_%')->delete();
            // Create new allowances
            foreach ($allowances as $allowance) {
                $type = $allowance['type'] ?? 'other';
                $note = '';
                if ($type === 'custom' && !empty($allowance['custom_name'])) {
                    $note = $allowance['custom_name'];
                    $type = 'custom';
                } else {
                    $note = $type;
                }
                $employee->incentives()->create([
                    'type' => 'allowance_' . $type,
                    'value' => $allowance['value'] ?? 0,
                    'note' => $note,
                ]);
            }
        }

        // Update incentives
        if (!empty($incentivesData)) {
            // Delete old incentives (non-allowance types)
            $employee->incentives()->where('type', 'not like', 'allowance_%')->delete();
            // Create new incentives
            foreach ($incentivesData as $incentive) {
                $type = $incentive['type'] ?? 'bonus';
                $note = '';
                if ($type === 'custom' && !empty($incentive['custom_name'])) {
                    $note = $incentive['custom_name'];
                }
                $employee->incentives()->create([
                    'type' => $type,
                    'value' => $incentive['value'] ?? 0,
                    'note' => $note,
                ]);
            }
        }

        // Update assets
        if (!empty($assets)) {
            foreach ($assets as $assetData) {
                $employee->assets()->create([
                    'name' => $assetData['name'] ?? '',
                    'description' => $assetData['description'] ?? '',
                    'type' => $assetData['type'] ?? 'fixed',
                    'value' => $assetData['value'] ?? 0,
                    'status' => 'active',
                ]);
            }
        }

        // Update fingerprints - only update if fingerprint has an id (already saved)
        // New fingerprints are added via the addFingerprint endpoint
        if (!empty($fingerprints)) {
            $fingerprintsWithId = array_filter($fingerprints, fn($fp) => !empty($fp['id']));
            foreach ($fingerprintsWithId as $fpData) {
                $employee->fingerprints()->where('id', $fpData['id'])->update([
                    'finger_position' => $fpData['finger_position'] ?? 'right',
                    'finger' => $fpData['finger'] ?? 'thumb',
                    'type' => $fpData['type'] ?? 'fingerprint',
                    'is_active' => true,
                ]);
            }
        }

        // Handle file uploads
        if ($r->hasFile('profile_photo')) {
            $path = $r->file('profile_photo')->store('photos', 'public');
            $employee->profile_photo = $path;
            $employee->save();
        }

        if ($r->hasFile('cv')) {
            $path = $r->file('cv')->store('cvs', 'public');
            $employee->cv = $path;
            $employee->save();
        }

        if ($r->hasFile('contract_file')) {
            $path = $r->file('contract_file')->store('contracts', 'public');
            $employee->contract_file = $path;
            $employee->save();
        }

        return response()->json([
            'data' => $employee->fresh(['department', 'incentives', 'assets', 'fingerprints']),
            'message' => 'تم تحديث الموظف بنجاح',
        ]);
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json([
            'message' => 'تم حذف الموظف بنجاح',
        ]);
    }

    /**
     * Terminate an employee
     */
    public function terminate(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'termination_type' => 'required|string|in:arbitrary,unjustified,mutual,performance,conduct,other',
            'termination_reason' => 'required|string',
        ]);

        $employee->status = 'terminated';
        $employee->termination_type = $data['termination_type'];
        $employee->termination_reason = $data['termination_reason'];
        $employee->termination_date = now()->toDateString();
        $employee->save();

        return response()->json([
            'data' => $employee,
            'message' => 'تم فصل الموظف بنجاح',
        ]);
    }

    /**
     * Restore an employee (cancel termination)
     */
    public function restore($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->status = 'active';
        $employee->termination_type = null;
        $employee->termination_reason = null;
        $employee->termination_date = null;
        $employee->save();

        return response()->json([
            'data' => $employee,
            'message' => 'تم إعادة تفعيل الموظف بنجاح',
        ]);
    }

    /**
     * Get fingerprints for an employee
     */
    public function getFingerprints($id)
    {
        $employee = Employee::findOrFail($id);
        $fingerprints = $employee->fingerprints()->with('device')->get();

        return response()->json([
            'data' => $fingerprints,
            'message' => 'تم جلب البصمات بنجاح',
        ]);
    }

    /**
     * Add fingerprint for an employee
     */
    public function addFingerprint(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'finger_id' => 'required|string',
            'finger_position' => 'nullable|string',
            'finger' => 'nullable|string',
            'attendance_device_id' => 'nullable|exists:attendance_devices,id',
            'template' => 'nullable|string',
            'type' => 'nullable|string|in:fingerprint,face',
        ]);

        $fingerprint = $employee->fingerprints()->create([
            'finger_id' => $data['finger_id'],
            'finger_position' => $data['finger_position'] ?? 'right',
            'finger' => $data['finger'] ?? 'thumb',
            'attendance_device_id' => $data['attendance_device_id'] ?? null,
            'template' => $data['template'] ?? null,
            'is_active' => true,
            'type' => $data['type'] ?? 'fingerprint',
        ]);

        return response()->json([
            'data' => $fingerprint,
            'message' => 'تم إضافة البصمة بنجاح',
        ]);
    }

    /**
     * Delete fingerprint
     */
    public function deleteFingerprint($id, $fingerprintId)
    {
        $employee = Employee::findOrFail($id);
        $fingerprint = $employee->fingerprints()->findOrFail($fingerprintId);
        $fingerprint->delete();

        return response()->json([
            'data' => null,
            'message' => 'تم حذف البصمة بنجاح',
        ]);
    }
}
