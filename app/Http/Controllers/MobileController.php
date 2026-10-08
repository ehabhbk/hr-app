<?php

namespace App\Http\Controllers;

use App\Models\AdvanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\MobileDevice;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * واجهات تطبيق الجوال (مدير + موظف).
 */
class MobileController extends Controller
{
    protected function authEmployee(Request $request)
    {
        return Employee::where('user_id', $request->user()->id)->first();
    }

    protected function isManager(Request $request): bool
    {
        $perms = $request->user()->role?->permissions ?? [];
        if (!is_array($perms)) {
            return false;
        }
        if (in_array('*', $perms)) {
            return true;
        }
        foreach (['employees.manage', 'employees.view', 'attendance.manage', 'menu.attendance'] as $p) {
            if (in_array($p, $perms)) {
                return true;
            }
        }
        return false;
    }

    /**
     * تسجيل رمز FCM للجهاز (يُستدعى عند فتح التطبيق).
     */
    public function registerToken(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => 'required|string|max:512',
            'platform' => 'nullable|string|in:android,ios',
        ]);

        $employee = $this->authEmployee($request);

        MobileDevice::updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'employee_id' => $employee?->id,
                'platform' => $data['platform'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['message' => 'تم تسجيل الجهاز بنجاح']);
    }

    /**
     * بياناتي: المستخدم + الموظف المرتبط + ملخص الشهر + المعلَّق.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('role');
        $employee = $this->authEmployee($request);

        $month = now()->month;
        $year = now()->year;
        $summary = null;
        $recent = [];

        if ($employee) {
            $records = AttendanceRecord::where('employee_id', $employee->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->orderBy('date', 'desc')
                ->get();

            // عدّ الأيام المميزة (عدة جلسات في اليوم تُحتسب يوماً واحداً)
            $dayOf = fn($r) => $r->date instanceof \Carbon\Carbon
                ? $r->date->format('Y-m-d')
                : (string) $r->date;
            $byDay = $records->groupBy($dayOf);
            $summary = [
                'total_days' => $byDay->count(),
                'present_days' => $byDay->filter(fn($rows) => $rows->contains(fn($r) => !empty($r->check_in_time)))->count(),
                'late_days' => $byDay->filter(fn($rows) => $rows->contains(fn($r) => $r->check_in_type === 'late'))->count(),
                'early_leave_days' => $byDay->filter(fn($rows) => $rows->contains(fn($r) => $r->check_out_type === 'early'))->count(),
                'absent_days' => $byDay->filter(fn($rows) => $rows->contains(fn($r) => $r->is_absent))->count(),
                'total_deduction' => (float) $records->sum('total_deduction'),
                'total_delay_minutes' => (int) $records->sum('check_in_delay_minutes'),
            ];
            $recent = $records->take(10)->values();

            $pendingAdvances = AdvanceRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')->count();
            $pendingLeaves = Leave::where('employee_id', $employee->id)
                ->where('status', 'pending')->count();
        } else {
            $pendingAdvances = AdvanceRequest::where('status', 'pending')->count();
            $pendingLeaves = Leave::where('status', 'pending')->count();
        }

        $unread = Notification::where(function ($q) use ($user) {
            $q->where('recipient_id', $user->id)->orWhere('user_id', $user->id);
        })->where('status', 'unread')->count();

        return response()->json([
            'user' => $user,
            'employee' => $employee ? $employee->load(['department', 'workShift']) : null,
            'is_manager' => $this->isManager($request) || !$employee,
            'month_summary' => $summary,
            'recent_records' => $recent,
            'pending_advances' => $pendingAdvances,
            'pending_leaves' => $pendingLeaves,
            'unread_notifications' => $unread,
        ]);
    }

    /**
     * صندوق وارد الإشعارات.
     */
    protected function inboxQuery(Request $request)
    {
        $uid = $request->user()->id;
        $empId = $this->authEmployee($request)?->id;
        return Notification::where(function ($q) use ($uid, $empId) {
            $q->where('recipient_id', $uid)->orWhere('user_id', $uid);
            if ($empId) {
                $q->orWhereJsonContains('data->employee_id', (int) $empId);
            }
        });
    }

    public function notifications(Request $request)
    {
        $items = $this->inboxQuery($request)
            ->orderBy('created_at', 'desc')->paginate(30);

        return response()->json($items);
    }

    public function readNotification(Request $request, $id)
    {
        $n = $this->inboxQuery($request)->where('id', $id)->firstOrFail();
        $n->markAsRead();

        return response()->json(['message' => 'تم التعليم كمقروء']);
    }

    /**
     * (مدير) إنشاء حساب دخول وربطه بموظف.
     */
    public function linkEmployee(Request $request)
    {
        if (!$this->isManager($request)) {
            return response()->json(['error' => 'ليس لديك صلاحية'], 403);
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
        ]);

        $role = Role::where('name', 'employee')->first();

        $user = User::create([
            'username' => $data['username'],
            // البريد إجباري في الجدول — يُولَّد داخلياً (الدخول باسم المستخدم)
            'email' => $data['username'] . '@hr.local',
            'password' => Hash::make($data['password']),
            'role_id' => $role?->id,
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $employee->user_id = $user->id;
        $employee->save();

        return response()->json([
            'message' => 'تم إنشاء حساب الموظف وربطه بنجاح',
            'username' => $user->username,
            'employee' => $employee->load('user'),
        ], 201);
    }

    /**
     * (مدير) قائمة الموظفين للجوال (مختصرة).
     */
    public function employees(Request $request)
    {
        if (!$this->isManager($request)) {
            return response()->json(['error' => 'ليس لديك صلاحية'], 403);
        }

        $q = Employee::with(['department', 'workShift', 'user'])
            ->when($request->search, fn($qq) => $qq->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->orderBy('name');

        return response()->json($q->paginate(30));
    }
}
