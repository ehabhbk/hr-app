<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\Warning;
use App\Models\Loan;
use App\Models\AdvanceRequest;
use App\Models\AttendanceDeviceLog;
use App\Models\Setting;
use App\Models\SalaryIncrease;
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

        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];

        $stats = [
            'total_employees' => Employee::where('status', 'active')->count(),
            'total_departments' => DB::table('departments')->count(),
            'new_hires_this_month' => Employee::whereMonth('hire_date', $currentMonth)
                ->whereYear('hire_date', $currentYear)
                ->where('status', 'active')
                ->count(),
            'resigned_this_month' => Employee::where('status', 'inactive')
                ->whereMonth('updated_at', $currentMonth)
                ->whereYear('updated_at', $currentYear)
                ->count(),
        ];

        $attendance = [
            'present_today' => AttendanceDeviceLog::whereDate('timestamp', $today)
                ->distinct('device_user_id')
                ->count('device_user_id'),
            'late_today' => $this->countLateArrivals($today),
            'absent_today' => $stats['total_employees'] - AttendanceDeviceLog::whereDate('timestamp', $today)
                ->distinct('device_user_id')
                ->count('device_user_id'),
        ];

        $attendance['attendance_rate'] = $stats['total_employees'] > 0
            ? round(($attendance['present_today'] / $stats['total_employees']) * 100, 1)
            : 0;

        $pending = [
            'leaves' => Leave::where('status', 'pending')->count(),
            'advances' => AdvanceRequest::where('status', 'pending')->count(),
            'warnings' => Warning::where('status', 'active')->whereMonth('date', $currentMonth)->count(),
        ];

        $monthlyPayroll = $this->calculateMonthlyPayroll($currentMonth, $currentYear);
        $yearlyIncreases = SalaryIncrease::whereYear('effective_date', $currentYear)->count();

        $recentActivities = $this->getRecentActivities();
        $departmentStats = $this->getDepartmentStats();
        $attendanceChart = $this->getAttendanceChart($currentMonth, $currentYear);
        $payrollTrend = $this->getPayrollTrend($currentYear);

        return response()->json([
            'organization' => [
                'name' => $orgData['name'] ?? 'Company Name',
                'address' => $orgData['address'] ?? '',
                'phone' => $orgData['phone'] ?? '',
                'email' => $orgData['email'] ?? '',
                'logo_url' => isset($orgData['logo']) ? asset('storage/' . $orgData['logo']) : null,
            ],
            'stats' => $stats,
            'attendance' => $attendance,
            'pending' => $pending,
            'payroll' => $monthlyPayroll,
            'recent_activities' => $recentActivities,
            'department_stats' => $departmentStats,
            'attendance_chart' => $attendanceChart,
            'payroll_trend' => $payrollTrend,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function countLateArrivals($date)
    {
        $expectedTime = '08:00:00';
        
        return AttendanceDeviceLog::whereDate('timestamp', $date)
            ->get()
            ->filter(function($log) use ($expectedTime) {
                if (!$log->timestamp) return false;
                $actualTime = date('H:i:s', strtotime($log->timestamp));
                return $actualTime > $expectedTime;
            })
            ->count();
    }

    private function calculateMonthlyPayroll($month, $year)
    {
        $employees = Employee::where('status', 'active')->get();
        $total = 0;

        foreach ($employees as $emp) {
            $base = $emp->base_salary ?? 0;
            $allowances = $emp->allowances->sum('amount');
            $total += $base + $allowances;
        }

        return [
            'total_gross' => $total,
            'total_net' => $total * 0.85,
            'total_deductions' => $total * 0.15,
            'employee_count' => $employees->count(),
            'avg_salary' => $employees->count() > 0 ? $total / $employees->count() : 0,
        ];
    }

    private function getRecentActivities()
    {
        $activities = [];

        $recentLeaves = Leave::with('employee')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        foreach ($recentLeaves as $leave) {
            $activities[] = [
                'type' => 'leave',
                'message' => "طلب {$leave->type} من {$leave->employee?->name}",
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
                DB::raw('SUM(employees.base_salary) as total_salary')
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

    private function getAttendanceChart($month, $year)
    {
        $chart = [];
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            if ($date->isFuture()) continue;

            $present = AttendanceDeviceLog::whereDate('date', $date)
                ->where('type', 'check_in')
                ->distinct('employee_id')
                ->count();

            $chart[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('d'),
                'day_name' => $date->locale('ar')->dayName,
                'present' => $present,
            ];
        }

        return $chart;
    }

    private function getPayrollTrend($year)
    {
        $trend = [];

        for ($month = 1; $month <= 12; $month++) {
            $employees = Employee::where('status', 'active')->get();
            $total = 0;

            foreach ($employees as $emp) {
                $base = $emp->base_salary ?? 0;
                $allowances = $emp->allowances->sum('amount');
                $total += $base + $allowances;
            }

            $trend[] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->locale('ar')->monthName,
                'total' => $total,
            ];
        }

        return $trend;
    }

    public function quickStats()
    {
        return response()->json([
            'total_employees' => Employee::where('status', 'active')->count(),
            'pending_leaves' => Leave::where('status', 'pending')->count(),
            'pending_advances' => AdvanceRequest::where('status', 'pending')->count(),
            'today_attendance' => AttendanceDeviceLog::whereDate('date', today())
                ->where('type', 'check_in')
                ->distinct('employee_id')
                ->count(),
        ]);
    }
}
