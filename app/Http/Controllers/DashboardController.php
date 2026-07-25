<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\Warning;
use App\Models\AdvanceRequest;
use App\Models\AttendanceDeviceLog;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\EmployeeEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $todayStr = $today->format('Y-m-d');

        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];

        // ==================== EMPLOYEE COUNTS ====================
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $terminatedEmployees = Employee::where('status', 'terminated')->count();
        $totalDepartments = DB::table('departments')->count();

        // Employees on approved leave right now
        $onLeaveEmployeeIds = Leave::where('status', 'approved')
            ->where('from_date', '<=', $todayStr)
            ->where('to_date', '>=', $todayStr)
            ->pluck('employee_id')
            ->unique();
        $onLeaveCount = $onLeaveEmployeeIds->count();

        // Employees assigned to a shift and active
        $employeesWithShift = Employee::whereNotNull('work_shift_id')
            ->where('status', 'active')
            ->get();

        // Currently working: employees who checked in today but haven't checked out
        $currentlyWorking = 0;
        $todayRecords = AttendanceRecord::where('date', $todayStr)->get();
        foreach ($employeesWithShift as $emp) {
            $isOnLeave = $onLeaveEmployeeIds->contains($emp->id);
            if ($isOnLeave) continue;
            $record = $todayRecords->firstWhere('employee_id', $emp->id);
            if ($record && $record->check_in_time && !$record->check_out_time) {
                $currentlyWorking++;
            }
        }

        // ==================== TODAY'S ATTENDANCE ====================
        $deviceUserIdsToday = AttendanceDeviceLog::whereDate('timestamp', $today)
            ->distinct('device_user_id')
            ->pluck('device_user_id');

        $presentToday = $deviceUserIdsToday->count();
        $lateToday = $this->countLateArrivals($today);

        // Absent today: employees with shift, active, not on leave, no fingerprint today
        $absentToday = 0;
        $notClockedToday = 0;
        foreach ($employeesWithShift as $emp) {
            $hasFingerprint = $deviceUserIdsToday->contains($emp->device_user_id);
            $isOnLeave = $onLeaveEmployeeIds->contains($emp->id);

            if (!$hasFingerprint && !$isOnLeave) {
                $absentToday++;
            }
            if (!$hasFingerprint) {
                $notClockedToday++;
            }
        }

        $attendanceRate = $activeEmployees > 0
            ? round(($presentToday / $activeEmployees) * 100, 1)
            : 0;

        // ==================== SALARY TOTALS ====================
        $totalBaseSalaries = Employee::where('status', 'active')->sum('base_salary');

        $totalGrossSalaries = 0;
        $activeEmpList = Employee::where('status', 'active')->with('compensations')->get();
        foreach ($activeEmpList as $emp) {
            $base = (float) ($emp->base_salary ?? 0);
            $positionAllowance = (float) ($emp->position_allowance ?? 0);
            $allowances = (float) $emp->compensations->where('is_recurring', true)->sum('value');
            $totalGrossSalaries += $base + $positionAllowance + $allowances;
        }

        // ==================== ADVANCES THIS MONTH ====================
        $advancesThisMonth = AdvanceRequest::where('status', 'approved')
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->sum('amount');

        // ==================== WARNINGS ====================
        $warningsThisMonth = Warning::where('status', 'active')
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->count();

        $warningsThisYear = Warning::where('status', 'active')
            ->whereYear('date', $currentYear)
            ->count();

        // ==================== LEAVES ====================
        $leavesThisMonth = Leave::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $leavesThisYear = Leave::whereYear('created_at', $currentYear)->count();

        // ==================== PIE CHARTS ====================
        // Warnings by type
        $warningsPie = [
            ['label' => 'إنذار كتابي', 'value' => Warning::where('type', 'written')->count(), 'color' => '#F59E0B'],
            ['label' => 'إنذار نهائي', 'value' => Warning::where('type', 'final')->count(), 'color' => '#EF4444'],
        ];

        // Leaves by type
        $allLeaves = Leave::where('status', 'approved')->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')->pluck('count', 'type');
        $leaveColors = [
            'official' => '#3B82F6', 'sick' => '#10B981', 'maternity' => '#EC4899',
            'hajj' => '#8B5CF6', 'unpaid' => '#6B7280',
        ];
        $leaveLabels = [
            'official' => 'رسمية', 'sick' => 'مرضية', 'maternity' => 'أمومة',
            'hajj' => 'حج', 'unpaid' => 'بدون مرتب',
        ];
        $leavesPie = [];
        foreach ($leaveLabels as $key => $label) {
            $leavesPie[] = [
                'label' => $label,
                'value' => (int) ($allLeaves[$key] ?? 0),
                'color' => $leaveColors[$key],
            ];
        }

        // Advances by type
        $shortAmount = AdvanceRequest::where('status', 'approved')
            ->where('type', 'short')->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)->sum('amount');
        $longAmount = AdvanceRequest::where('status', 'approved')
            ->where('type', 'long')->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)->sum('amount');
        $advancesPie = [
            ['label' => 'سلفة قصيرة', 'value' => $shortAmount, 'color' => '#10B981'],
            ['label' => 'سلفة طويلة', 'value' => $longAmount, 'color' => '#3B82F6'],
        ];

        // Attendance pie
        $attendancePie = [
            ['label' => 'حضور', 'value' => max(0, $presentToday - $lateToday), 'color' => '#10B981'],
            ['label' => 'متأخر', 'value' => $lateToday, 'color' => '#F59E0B'],
            ['label' => 'غياب', 'value' => $absentToday, 'color' => '#EF4444'],
            ['label' => 'في إجازة', 'value' => $onLeaveCount, 'color' => '#8B5CF6'],
        ];

        // Rejected leaves by type
        $rejectedLeaves = Leave::where('status', 'rejected')->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')->pluck('count', 'type');
        $rejectedLeavesPie = [];
        foreach ($leaveLabels as $key => $label) {
            $rejectedLeavesPie[] = [
                'label' => $label, 'value' => (int) ($rejectedLeaves[$key] ?? 0),
                'color' => $leaveColors[$key],
            ];
        }

        // Pending leaves by type
        $pendingLeavesQ = Leave::where('status', 'pending')->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')->pluck('count', 'type');
        $pendingLeavesPie = [];
        foreach ($leaveLabels as $key => $label) {
            $pendingLeavesPie[] = [
                'label' => $label, 'value' => (int) ($pendingLeavesQ[$key] ?? 0),
                'color' => $leaveColors[$key],
            ];
        }

        // Leave requests status (approved vs rejected vs pending)
        $leaveStatusPie = [
            ['label' => 'موافق عليها', 'value' => Leave::where('status', 'approved')->count(), 'color' => '#10B981'],
            ['label' => 'مرفوضة', 'value' => Leave::where('status', 'rejected')->count(), 'color' => '#EF4444'],
            ['label' => 'معلقة', 'value' => Leave::where('status', 'pending')->count(), 'color' => '#F59E0B'],
        ];

        // All advance requests by type (count)
        $shortCount = AdvanceRequest::where('type', 'short')->count();
        $longCount = AdvanceRequest::where('type', 'long')->count();
        $advancesCountPie = [
            ['label' => 'سلفة قصيرة', 'value' => $shortCount, 'color' => '#10B981'],
            ['label' => 'سلفة طويلة', 'value' => $longCount, 'color' => '#3B82F6'],
        ];

        // Departments by employee count
        $depts = DB::table('departments')
            ->leftJoin('employees', 'departments.id', '=', 'employees.department_id')
            ->where('employees.status', 'active')
            ->select('departments.id', 'departments.name', DB::raw('COUNT(employees.id) as emp_count'))
            ->groupBy('departments.id', 'departments.name')
            ->get();
        $deptColors = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#F97316','#6366F1','#14B8A6'];
        $deptEmployeesPie = [];
        $deptSalaryPie = [];
        foreach ($depts as $i => $d) {
            $color = $deptColors[$i % count($deptColors)];
            $deptEmployeesPie[] = ['label' => $d->name, 'value' => (int) $d->emp_count, 'color' => $color];
            $totalSal = DB::table('employees')->where('department_id', $d->id)
                ->where('status', 'active')->sum('base_salary');
            $deptSalaryPie[] = ['label' => $d->name, 'value' => (float) $totalSal, 'color' => $color];
        }

        // Job positions per department
        $deptPositions = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.status', 'active')
            ->whereNotNull('employees.position')
            ->select('departments.id', 'departments.name', DB::raw('COUNT(DISTINCT employees.position) as position_count'))
            ->groupBy('departments.id', 'departments.name')
            ->get();
        $deptPositionsPie = [];
        foreach ($deptPositions as $i => $d) {
            $color = $deptColors[$i % count($deptColors)];
            $deptPositionsPie[] = ['label' => $d->name, 'value' => (int) $d->position_count, 'color' => $color];
        }

        // Job positions distribution (employee count per title)
        $positions = Employee::where('status', 'active')
            ->whereNotNull('position')
            ->selectRaw('position, COUNT(*) as count')
            ->groupBy('position')
            ->orderByDesc('count')
            ->get();
        $posColors = ['#6366F1','#EC4899','#10B981','#F59E0B','#3B82F6','#EF4444','#8B5CF6','#06B6D4','#F97316','#14B8A6'];
        $positionsPie = [];
        foreach ($positions as $i => $p) {
            $color = $posColors[$i % count($posColors)];
            $positionsPie[] = ['label' => $p->position, 'value' => (int) $p->count, 'color' => $color];
        }

        // ==================== IDEAL EMPLOYEE ====================
        $idealEmployee = $this->getIdealEmployee($currentMonth, $currentYear);

        // ==================== EXPIRING CONTRACTS ====================
        $expiringContracts = Employee::whereNotNull('contract_end_date')
            ->where('contract_end_date', '>=', now())
            ->where('contract_end_date', '<=', now()->addDays(30))
            ->where('status', '!=', 'terminated')
            ->orderBy('contract_end_date')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'contract_end_date' => $e->contract_end_date->format('Y-m-d'),
                'days_remaining' => (int)now()->diffInDays($e->contract_end_date, false),
            ]);

        // ==================== PENDING REQUESTS ====================
        $pendingLeaves = Leave::where('status', 'pending')->count();
        $pendingAdvances = AdvanceRequest::where('status', 'pending')->count();

        // ==================== RECENT ACTIVITIES ====================
        $recentActivities = $this->getRecentActivities();

        // ==================== DEPARTMENT STATS ====================
        $departmentStats = $this->getDepartmentStats();

        return response()->json([
            'organization' => [
                'name' => $orgData['name'] ?? 'Company Name',
                'address' => $orgData['address'] ?? '',
                'phone' => $orgData['phone'] ?? '',
                'email' => $orgData['email'] ?? '',
                'logo_url' => isset($orgData['logo']) ? asset('storage/' . $orgData['logo']) : null,
            ],
            'stats' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'total_departments' => $totalDepartments,
                'terminated_employees' => $terminatedEmployees,
                'on_leave_now' => $onLeaveCount,
                'total_base_salaries' => $totalBaseSalaries,
                'total_gross_salaries' => $totalGrossSalaries,
                'absences_today' => $absentToday,
                'currently_working' => $currentlyWorking,
                'not_clocked_today' => $notClockedToday,
                'late_today' => $lateToday,
                'attendance_rate' => $attendanceRate,
                'advances_this_month' => $advancesThisMonth,
                'warnings_this_month' => $warningsThisMonth,
                'warnings_this_year' => $warningsThisYear,
                'leaves_this_month' => $leavesThisMonth,
                'leaves_this_year' => $leavesThisYear,
                'employees_with_shifts' => $employeesWithShift->count(),
            ],
            'pie_charts' => [
                'warnings' => $warningsPie,
                'leaves' => $leavesPie,
                'advances' => $advancesPie,
                'attendance' => $attendancePie,
                'departments_employees' => $deptEmployeesPie,
                'departments_salary' => $deptSalaryPie,
                'positions' => $positionsPie,
                'dept_positions' => $deptPositionsPie,
                'leaves_rejected' => $rejectedLeavesPie,
                'leaves_pending' => $pendingLeavesPie,
                'leaves_status' => $leaveStatusPie,
                'advances_count' => $advancesCountPie,
            ],
            'pending' => [
                'leaves' => $pendingLeaves,
                'advances' => $pendingAdvances,
            ],
            'ideal_employee' => $idealEmployee,
            'recent_activities' => $recentActivities,
            'department_stats' => $departmentStats,
            'expiring_contracts' => $expiringContracts,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function countLateArrivals($date)
    {
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : $date;

        // Use attendance_records which already have check_in_type calculated by processing
        $lateRecords = AttendanceRecord::whereDate('date', $dateStr)
            ->where('check_in_type', 'late')
            ->count();

        if ($lateRecords > 0) return $lateRecords;

        // Fallback: use raw device logs - group by device_user_id, take first log only
        $logs = AttendanceDeviceLog::whereDate('timestamp', $dateStr)
            ->orderBy('timestamp')
            ->get()
            ->groupBy('device_user_id');

        $lateCount = 0;
        foreach ($logs as $userId => $userLogs) {
            $firstLog = $userLogs->first();
            if (!$firstLog || !$firstLog->timestamp) continue;

            $logTime = $firstLog->timestamp instanceof Carbon
                ? $firstLog->timestamp
                : Carbon::parse($firstLog->timestamp);

            // Get employee to check shift start time
            $employee = Employee::where('device_user_id', $userId)->first();
            if (!$employee) continue;

            $expectedTime = '08:00';
            if ($employee->work_shift_id) {
                $shift = \App\Models\WorkShift::find($employee->work_shift_id);
                if ($shift && $shift->start_time) {
                    $expectedTime = $shift->start_time;
                }
            }

            $expected = Carbon::parse($dateStr . ' ' . $expectedTime);
            if ($logTime->gt($expected)) {
                $lateCount++;
            }
        }

        return $lateCount;
    }

    private function getRecentActivities()
    {
        $activities = [];

        $recentLeaves = Leave::with('employee')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        foreach ($recentLeaves as $leave) {
            $typeMap = [
                'official' => 'رسمية', 'sick' => 'مرضية', 'maternity' => 'أمومة',
                'hajj' => 'حج', 'unpaid' => 'بدون مرتب',
            ];
            $typeName = $typeMap[$leave->type] ?? $leave->type;
            $activities[] = [
                'type' => 'leave',
                'message' => "إجازة {$typeName} من {$leave->employee?->name}",
                'status' => $leave->status,
                'date' => $leave->created_at->toDateTimeString(),
            ];
        }

        $recentWarnings = Warning::with('employee')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();
        foreach ($recentWarnings as $warning) {
            $activities[] = [
                'type' => 'warning',
                'message' => "إنذار لـ {$warning->employee?->name}",
                'status' => $warning->status,
                'date' => $warning->created_at->toDateTimeString(),
            ];
        }

        $recentAdvances = AdvanceRequest::with('employee')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();
        foreach ($recentAdvances as $adv) {
            $typeName = $adv->type === 'short' ? 'سلفة قصيرة' : 'سلفة طويلة';
            $activities[] = [
                'type' => 'advance',
                'message' => "{$typeName} من {$adv->employee?->name}",
                'status' => $adv->status,
                'date' => $adv->created_at->toDateTimeString(),
            ];
        }

        usort($activities, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return array_slice($activities, 0, 10);
    }

    private function getDepartmentStats()
    {
        return DB::table('departments')
            ->leftJoin('employees', 'departments.id', '=', 'employees.department_id')
            ->where('employees.status', 'active')
            ->select(
                'departments.id',
                'departments.name',
                DB::raw('COUNT(employees.id) as employee_count'),
                DB::raw('COALESCE(SUM(employees.base_salary), 0) as total_salary')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'employee_count' => $d->employee_count ?? 0,
                'total_salary' => $d->total_salary ?? 0,
            ]);
    }

    private function getIdealEmployee($month, $year)
    {
        $employees = Employee::whereIn('status', ['active', 'vacation'])
            ->with(['warningsRelation', 'leaves', 'shiftAssignments'])
            ->get();

        $period = sprintf('%04d-%02d', $year, $month);

        $evaluations = $employees->map(function($emp) use ($month, $year, $period) {
            $base = (float) ($emp->base_salary ?? 0);

            // Attendance score from attendance_records
            $records = AttendanceRecord::where('employee_id', $emp->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get();

            $lateDays = $records->where('check_in_type', 'late')->count();
            $absentDays = $records->where('is_absent', true)->count();
            $earlyLeaveDays = $records->where('check_out_type', 'early')->count();

            // Scores
            $attendanceScore = max(0, 100 - ($lateDays * 5) - ($absentDays * 10) - ($earlyLeaveDays * 3));

            $leaveCount = $emp->leaves->filter(function($l) use ($month, $year) {
                return $l->status === 'approved'
                    && $l->from_date->month == $month
                    && $l->from_date->year == $year;
            })->count();
            $leaveScore = max(0, 100 - ($leaveCount * 10));

            $warningCount = $emp->warningsRelation->filter(function($w) use ($month, $year) {
                return $w->status === 'active'
                    && $w->date
                    && date('m', strtotime($w->date)) == $month
                    && date('Y', strtotime($w->date)) == $year;
            })->count();
            $warningScore = max(0, 100 - ($warningCount * 25));

            $totalScore = round(($attendanceScore + $leaveScore + $warningScore) / 3, 1);

            // Manual evaluation stars
            $manualEval = EmployeeEvaluation::where('employee_id', $emp->id)
                ->where('period', $period)
                ->first();

            $starsTotal = 0;
            if ($manualEval) {
                $starsTotal = (int) ($manualEval->appearance ?? 0)
                    + (int) ($manualEval->behavior ?? 0)
                    + (int) ($manualEval->performance ?? 0);
            }

            $combinedScore = $manualEval
                ? round($totalScore * 0.7 + ($starsTotal / 30 * 100) * 0.3, 1)
                : round($totalScore * 0.85, 1);

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'position' => $emp->position,
                'department' => $emp->department?->name,
                'profile_photo' => $emp->profile_photo,
                'attendance_score' => $attendanceScore,
                'leave_score' => $leaveScore,
                'warning_score' => $warningScore,
                'total_score' => $totalScore,
                'stars' => $manualEval ? [
                    'appearance' => $manualEval->appearance,
                    'behavior' => $manualEval->behavior,
                    'performance' => $manualEval->performance,
                    'total' => $starsTotal,
                ] : null,
                'combined_score' => $combinedScore,
            ];
        });

        $ideal = $evaluations->sortByDesc('combined_score')->first();

        return $ideal ?: null;
    }

    public function quickStats()
    {
        $today = Carbon::today();

        $deviceUserIdsToday = AttendanceDeviceLog::whereDate('timestamp', $today)
            ->distinct('device_user_id')
            ->pluck('device_user_id');

        return response()->json([
            'total_employees' => Employee::where('status', 'active')->count(),
            'pending_leaves' => Leave::where('status', 'pending')->count(),
            'pending_advances' => AdvanceRequest::where('status', 'pending')->count(),
            'today_attendance' => $deviceUserIdsToday->count(),
        ]);
    }
}
