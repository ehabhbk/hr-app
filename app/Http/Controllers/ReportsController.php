<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Incentive;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Leave;
use App\Models\Warning;
use App\Models\AttendanceDeviceLog;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\PayrollRecord;
use App\Models\SalaryIncrease;
use App\Models\ReportLog;
use App\Models\LetterLog;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    private function checkPermission($request, $permission) {
        $user = $request->user();
        $permissions = $user->role?->permissions ?? [];
        $isAdmin = in_array('*', $permissions);
        
        if (!$isAdmin && !in_array($permission, $permissions) && !in_array('reports.view', $permissions)) {
            return response()->json(['error' => 'ليس لديك صلاحية عرض هذا التقرير'], 403);
        }
        return null;
    }

    public function salaryReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.salary')) {
            return $error;
        }
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');
        $employeeId = $request->input('employee_id');

        // Get settings
        $salarySettings = Setting::where('key', 'financials')->first();
        $salaryData = $salarySettings ? $salarySettings->value : [];
        $currency = $salaryData['default_currency'] ?? 'SDG';
        $currencyLabel = $salaryData['currency_symbol'] ?? 'جنيه سوداني';

        $query = Employee::with([
            'department',
            'compensations' => function($q) {
                // ALL records (fixed + one-time), no month filter — we split by is_recurring in PHP
                $q->orderBy('created_at', 'desc');
            },
            'advances' => function($q) use ($month, $year) {
                // Short advances: only current month
                // Long advances: all approved with remaining > 0 (deduct monthly)
                $q->where(function($sub) use ($month, $year) {
                    $sub->where('status', 'approved')
                        ->where('remaining_amount', '>', 0)
                        ->where('type', 'long');
                })->orWhere(function($sub) use ($month, $year) {
                    $sub->where('status', 'approved')
                        ->where('remaining_amount', '>', 0)
                        ->where('type', 'short')
                        ->whereMonth('date', $month)
                        ->whereYear('date', $year);
                });
            },
            'deductions' => function($q) use ($month, $year) {
                // One-time button deductions: current month only
                $q->whereMonth('date', $month)->whereYear('date', $year);
            },
            'attendanceRecords' => function($q) use ($month, $year) {
                $q->whereMonth('date', $month)
                  ->whereYear('date', $year);
            },
        ])->where('status', 'active');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($employeeId) {
            $query->where('id', $employeeId);
        }

        $employees = $query->get();

        $data = $employees->map(function($emp) use ($month, $year, $salaryData) {
            $baseSalary = (float) ($emp->base_salary ?? 0);
            $positionAllowance = (float) ($emp->position_allowance ?? 0);
            
            // Split compensations into fixed (recurring) and one-time (button incentives)
            $fixedAllowances = $emp->compensations->where('is_recurring', true);
            $oneTimeIncentives = $emp->compensations->filter(function($item) use ($month, $year) {
                return !$item->is_recurring
                    && $item->date
                    && date('m', strtotime($item->date)) == str_pad($month, 2, '0', STR_PAD_LEFT)
                    && date('Y', strtotime($item->date)) == $year;
            });

            $allowancesList = $fixedAllowances->map(fn($a) => [
                'type' => $a->type ?? 'other',
                'name' => $this->getAllowanceName($a->type),
                'amount' => (float) $a->value,
            ])->values()->toArray();
            $totalAllowances = (float) $fixedAllowances->sum('value');

            $incentivesList = $oneTimeIncentives->map(fn($i) => [
                'type' => $i->type,
                'name' => $this->getIncentiveName($i->type),
                'amount' => (float) $i->value,
            ])->values()->toArray();
            $totalIncentives = array_sum(array_column($incentivesList, 'amount'));
            
            // Calculate gross
            $grossSalary = $baseSalary + $positionAllowance + $totalAllowances + $totalIncentives;
            
            // Insurance
            $insuranceType = $emp->insurance_type ?? 'none';
            $insuranceAmount = (float) ($emp->insurance_amount ?? 0);
            
            // Get deductions (for current month only — filtered by eager loading)
            $otherDeductions = (float) $emp->deductions->sum('amount');
            
            // Get advance settings for deduction percentages
            $advanceSettings = Setting::where('key', 'advances')->first();
            $advanceConfig = $advanceSettings ? $advanceSettings->value : [];
            $shortAdvanceConfig = $advanceConfig['short_advance'] ?? [];
            $longAdvanceConfig = $advanceConfig['long_advance'] ?? [];
            
            // Short advance deduction percentage (from settings)
            $shortDeductionPercent = (float) ($shortAdvanceConfig['deduction_percent'] ?? 100) / 100;
            // Short advance max percentage of gross salary (from settings)
            $shortMaxPercent = (float) ($shortAdvanceConfig['max_percent'] ?? 50) / 100;
            
            // Calculate advance deductions based on type
            // - short: deduct based on deduction_percent of gross salary, max is max_percent of gross
            // - long: deduct monthly installment from the advance
            $totalAdvanceDeduction = 0;
            $totalCarriedDeduction = 0;
            $advancesList = [];
            
            foreach ($emp->advances as $advance) {
                if (($advance->remaining_amount ?? 0) <= 0) continue;
                if ($advance->status !== 'approved') continue;
                
                $remainingAmount = (float) ($advance->remaining_amount ?? 0);
                $monthlyInstallment = (float) ($advance->monthly_installment ?? 0);
                $isLongTerm = $advance->type === 'long';
                
                $deductAmount = 0;
                
                if ($isLongTerm) {
                    // Long term advance: deduct monthly installment only
                    $deductAmount = min($monthlyInstallment, $remainingAmount);
                } else {
                    // Short term advance: deduct based on deduction_percent of gross salary
                    // Maximum is max_percent of gross salary
                    $maxDeduction = min(
                        $grossSalary * $shortDeductionPercent,
                        $grossSalary * $shortMaxPercent
                    );
                    $deductAmount = min($maxDeduction, $remainingAmount);
                }
                
                $totalAdvanceDeduction += $deductAmount;
                
                // Calculate remaining after this deduction
                $newRemaining = $remainingAmount - $deductAmount;
                
                $advancesList[] = [
                    'id' => $advance->id,
                    'amount' => (float) $advance->amount,
                    'remaining_before' => $remainingAmount,
                    'deducted' => $deductAmount,
                    'remaining_after' => max(0, $newRemaining),
                    'type' => $isLongTerm ? 'طويل' : 'قصير',
                    'carry_over' => max(0, -$newRemaining),
                ];
            }
            
            // Calculate attendance deductions (late arrivals, early leaves)
            $attendanceRecords = $emp->attendanceRecords ?? collect();
            // Include records with total_deduction > 0 (regardless of deduction_applied flag)
            $attendanceDeductions = $attendanceRecords
                ->filter(function($record) {
                    // Include if deduction_applied is true OR if total_deduction > 0
                    return $record->deduction_applied || ($record->total_deduction ?? 0) > 0;
                })
                ->sum('total_deduction');
            $attendanceDeductionCount = $attendanceRecords->where('has_delay', true)->count();
            $lateDays = $attendanceRecords->where('check_in_type', 'late')->count();
            $earlyLeaveDays = $attendanceRecords->where('check_out_type', 'early')->count();
            $excusedDelays = $attendanceRecords->where('delay_excused', true)->count();
            
            // Calculate tax from basic salary only
            $taxableAmount = $baseSalary;
            $tax = $this->calculateIncomeTax($taxableAmount);
            
            // Calculate net salary: gross - insurance - deductions - attendance - advance - tax
            $actualAdvanceDeduction = min($totalAdvanceDeduction, max(0, $grossSalary - $insuranceAmount - $otherDeductions - $attendanceDeductions));
            $carriedAdvanceDeduction = max(0, $totalAdvanceDeduction - $actualAdvanceDeduction);
            $netSalary = $grossSalary - $insuranceAmount - $otherDeductions - $attendanceDeductions - $actualAdvanceDeduction - $tax;
            
            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_code' => $emp->employee_number ?? $emp->file_number ?? $emp->id,
                'department' => $emp->department?->name ?? '-',
                'department_id' => $emp->department_id,
                'job_title' => $emp->position ?? '-',
                'base_salary' => $baseSalary,
                'position_allowance' => $positionAllowance,
                'allowances' => $allowancesList,
                'total_allowances' => $totalAllowances,
                'incentives' => $incentivesList,
                'total_incentives' => $totalIncentives,
                'gross_salary' => $grossSalary,
                'insurance_type' => $insuranceType,
                'insurance_amount' => $insuranceAmount,
                'deductions' => $otherDeductions,
                'attendance_deductions' => $attendanceDeductions,
                'attendance_details' => [
                    'late_days' => $lateDays,
                    'early_leave_days' => $earlyLeaveDays,
                    'excused_delays' => $excusedDelays,
                ],
                'advance_deductions' => $actualAdvanceDeduction,
                'advance_deductions_planned' => $totalAdvanceDeduction,
                'advance_carry_over' => $carriedAdvanceDeduction,
                'advances_list' => $advancesList,
                'income_tax' => $tax,
                'total_deductions' => $insuranceAmount + $otherDeductions + $attendanceDeductions + $actualAdvanceDeduction + $tax,
                'net_salary' => $netSalary,
                'month' => $month,
                'year' => $year,
            ];
        });

        ReportLog::logReport('salary', 'كشف المرتبات الشهري', 
            ['month' => $month, 'year' => $year],
            ['department_id' => $departmentId, 'employee_id' => $employeeId]
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'month' => $month,
                'year' => $year,
                'total_employees' => $data->count(),
                'currency' => $currency,
                'currency_label' => $currencyLabel,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'tax_brackets' => $this->getTaxBrackets(),
            ],
        ]);
    }

    public function incomeTaxReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.income_tax')) {
            return $error;
        }
        
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');

        $query = Employee::with([
            'department',
            'compensations'
        ])->where('status', 'active');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->get();

        $data = $employees->map(function($emp) use ($year) {
            $baseSalary = (float) ($emp->base_salary ?? 0);

            $monthlyTax = $this->calculateIncomeTax($baseSalary);
            $annualTax = $monthlyTax * 12;

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_code' => $emp->employee_number ?? $emp->file_number ?? $emp->id,
                'department' => $emp->department?->name ?? '-',
                'job_title' => $emp->position ?? '-',
                'monthly_salary' => $baseSalary,
                'annual_salary' => $baseSalary * 12,
                'annual_tax' => round($annualTax, 2),
                'monthly_tax' => $monthlyTax,
                'tax_brackets' => $this->getTaxBrackets(),
                'year' => $year,
            ];
        });

        ReportLog::logReport('income_tax', 'تقرير ضريبة الدخل', ['year' => $year]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'year' => $year,
                'total_employees' => $data->count(),
                'tax_brackets' => $this->getTaxBrackets(),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function salaryIncreaseReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.salary_increase')) {
            return $error;
        }
        
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');

        $query = SalaryIncrease::with([
            'employee.department',
        ])->whereYear('effective_date', $year);

        if ($departmentId) {
            $query->whereHas('employee', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $increases = $query->orderBy('effective_date', 'desc')->get();

        $data = $increases->map(function($item) {
            return [
                'id' => $item->id,
                'employee_id' => $item->employee_id,
                'name' => $item->employee?->name ?? '-',
                'employee_code' => $item->employee?->employee_number ?? $item->employee?->file_number ?? '-',
                'department' => $item->employee?->department?->name ?? '-',
                'job_title' => $item->employee?->position ?? '-',
                'old_salary' => (float) $item->old_salary,
                'new_salary' => (float) $item->new_salary,
                'increase_amount' => (float) $item->increase_amount,
                'increase_percent' => (float) $item->increase_percent,
                'effective_date' => $item->effective_date,
                'reason' => $item->reason ?? '-',
                'status' => $item->status,
                'created_at' => $item->created_at,
            ];
        });

        ReportLog::logReport('salary_increase', 'تقرير الزيادة السنوية', ['year' => $year]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'year' => $year,
                'total_increases' => $data->count(),
                'total_amount' => $data->sum('increase_amount'),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function leaveWarningReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.leaves_warnings')) {
            return $error;
        }
        
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');

        $query = Employee::with([
            'department',
            'leaves' => function($q) use ($year) {
                $q->whereYear('from_date', $year);
            },
            'warningsRelation' => function($q) use ($year) {
                $q->whereYear('date', $year);
            }
        ])->where('status', 'active');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->get();

        $data = $employees->map(function($emp) use ($year) {
            $leaves = $emp->leaves;
            $warnings = $emp->warningsRelation;
            
            $leaveByType = $leaves->groupBy('type')->map(function($items) {
                return [
                    'count' => $items->count(),
                    'days' => $items->sum(function($l) {
                        return Carbon::parse($l->from_date)->diffInDays(Carbon::parse($l->to_date)) + 1;
                    }),
                    'details' => $items->map(fn($l) => [
                        'id' => $l->id,
                        'from_date' => $l->from_date,
                        'to_date' => $l->to_date,
                        'days' => Carbon::parse($l->from_date)->diffInDays(Carbon::parse($l->to_date)) + 1,
                        'status' => $l->status,
                        'paid' => $l->paid,
                    ])
                ];
            });

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_code' => $emp->employee_number ?? $emp->file_number ?? $emp->id,
                'department' => $emp->department?->name ?? '-',
                'department_id' => $emp->department_id,
                'job_title' => $emp->position ?? '-',
                'hire_date' => $emp->hire_date,
                'leaves' => [
                    'total_count' => $leaves->count(),
                    'total_days' => $leaves->sum(function($l) {
                        return Carbon::parse($l->from_date)->diffInDays(Carbon::parse($l->to_date)) + 1;
                    }),
                    'by_type' => $leaveByType,
                ],
                'warnings' => [
                    'total_count' => $warnings->count(),
                    'details' => $warnings->map(fn($w) => [
                        'id' => $w->id,
                        'date' => $w->date,
                        'reason' => $w->reason,
                        'type' => $w->type,
                        'status' => $w->status,
                    ])
                ],
                'year' => $year,
            ];
        });

        ReportLog::logReport('leave_warning', 'تقرير الإجازات والانذارات', ['year' => $year]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'year' => $year,
                'total_employees' => $data->count(),
                'total_leaves' => $data->sum('leaves.total_count'),
                'total_warnings' => $data->sum('warnings.total_count'),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function employeeEvaluationReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.evaluation')) {
            return $error;
        }
        
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');

        $query = Employee::with([
            'department',
            'warningsRelation',
            'leaves' => function($q) use ($year) {
                $q->whereYear('from_date', $year);
            },
            'shiftAssignments'
        ])->where('status', 'active');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->get();

        $attendanceData = AttendanceDeviceLog::select('device_user_id', 'timestamp')
            ->whereYear('timestamp', $year)
            ->get()
            ->groupBy('device_user_id');

        $evaluations = $employees->map(function($emp) use ($year, $attendanceData) {
            $deviceUserId = $emp->device_user_id ?? $emp->attendance_device_user_id;
            $empAttendance = $attendanceData->get($deviceUserId, collect());
            
            $totalDays = $empAttendance->count();
            $lateDays = 0;
            $earlyLeaveDays = 0;
            
            $uniqueDates = [];
            foreach ($empAttendance as $log) {
                $logDate = date('Y-m-d', strtotime($log->timestamp));
                if (!isset($uniqueDates[$logDate])) {
                    $uniqueDates[$logDate] = true;
                    $actualTime = date('H:i:s', strtotime($log->timestamp));
                    if ($actualTime > '08:00:00') {
                        $lateMinutes = (strtotime($actualTime) - strtotime('08:00:00')) / 60;
                        if ($lateMinutes > 15) $lateDays++;
                    }
                    if ($actualTime < '17:00:00') {
                        $earlyLeaveDays++;
                    }
                }
            }

            $leavesCount = $emp->leaves->count();
            $warningsCount = $emp->warningsRelation->count();
            
            $attendanceScore = max(0, 100 - ($lateDays * 5) - ($earlyLeaveDays * 3));
            $leaveScore = max(0, 100 - ($leavesCount * 5));
            $warningScore = max(0, 100 - ($warningsCount * 20));
            $totalScore = round(($attendanceScore + $leaveScore + $warningScore) / 3, 2);

            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_code' => $emp->employee_number ?? $emp->file_number ?? $emp->id,
                'department' => $emp->department?->name ?? '-',
                'department_id' => $emp->department_id,
                'job_title' => $emp->position ?? '-',
                'attendance_days' => $totalDays,
                'late_days' => $lateDays,
                'early_leave_days' => $earlyLeaveDays,
                'leave_count' => $leavesCount,
                'warning_count' => $warningsCount,
                'attendance_score' => $attendanceScore,
                'leave_score' => $leaveScore,
                'warning_score' => $warningScore,
                'total_score' => $totalScore,
                'year' => $year,
            ];
        })->sortByDesc('total_score');

        $bestEmployees = $evaluations->take(5)->values();
        $worstEmployees = $evaluations->sortBy('total_score')->take(5)->values();

        ReportLog::logReport('evaluation', 'تقرير تقييم الموظفين', ['year' => $year]);

        return response()->json([
            'best_employees' => $bestEmployees,
            'worst_employees' => $worstEmployees,
            'all_employees' => $evaluations->values(),
            'meta' => [
                'year' => $year,
                'total_employees' => $evaluations->count(),
                'average_score' => round($evaluations->avg('total_score'), 2),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function departmentReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.department')) {
            return $error;
        }
        
        $year = $request->input('year', now()->year);
        
        $departments = DB::table('departments')
            ->leftJoin('employees', 'departments.id', '=', 'employees.department_id')
            ->select(
                'departments.id',
                'departments.name',
                DB::raw('COUNT(employees.id) as employee_count'),
                DB::raw('SUM(employees.base_salary) as total_salaries'),
                DB::raw('AVG(employees.base_salary) as avg_salary')
            )
            ->where('employees.status', 'active')
            ->groupBy('departments.id', 'departments.name')
            ->get();

        $data = $departments->map(function($dept) {
            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'employee_count' => $dept->employee_count ?? 0,
                'total_salaries' => (float) ($dept->total_salaries ?? 0),
                'avg_salary' => round((float) ($dept->avg_salary ?? 0), 2),
            ];
        });

        ReportLog::logReport('department', 'تقرير الأقسام', ['year' => $year]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'total_departments' => $data->count(),
                'total_employees' => $data->sum('employee_count'),
                'total_salaries' => $data->sum('total_salaries'),
            ]
        ]);
    }

    public function summary(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.dashboard')) {
            return $error;
        }
        
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];
        
        $stats = [
            'total_employees' => Employee::where('status', 'active')->count(),
            'total_departments' => DB::table('departments')->count(),
            'total_leaves_pending' => Leave::where('status', 'pending')->count(),
            'total_warnings' => Warning::whereYear('date', now()->year)->count(),
            'total_increases' => SalaryIncrease::whereYear('effective_date', now()->year)->count(),
            'reports_generated' => ReportLog::whereMonth('generated_at', now()->month)->count(),
            'letters_generated' => LetterLog::whereMonth('generated_at', now()->month)->count(),
            'total_salaries' => Employee::where('status', 'active')->sum('base_salary'),
        ];
        
        return response()->json([
            'organization' => [
                'name' => $orgData['name'] ?? 'Company Name',
                'address' => $orgData['address'] ?? '',
                'phone' => $orgData['phone'] ?? '',
                'email' => $orgData['email'] ?? '',
                'tax_number' => $orgData['tax_number'] ?? '',
                'logo_url' => isset($orgData['logo']) ? asset('storage/' . $orgData['logo']) : null,
            ],
            'stats' => $stats,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function reportHistory(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.history')) {
            return $error;
        }
        
        $type = $request->input('type');
        
        $query = ReportLog::with('user')->orderBy('generated_at', 'desc');
        
        if ($type) {
            $query->where('report_type', $type);
        }
        
        $reports = $query->paginate(20);
        
        return response()->json($reports);
    }

    public function letterHistory(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.letters')) {
            return $error;
        }
        
        $type = $request->input('type');
        $employeeId = $request->input('employee_id');
        
        $query = LetterLog::with(['user', 'employee'])->orderBy('generated_at', 'desc');
        
        if ($type) {
            $query->where('letter_type', $type);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        
        $letters = $query->paginate(20);
        
        return response()->json($letters);
    }

    private function calculateIncomeTax($monthlySalary)
    {
        $brackets = $this->getTaxBrackets();
        $tax = 0;
        $previousLimit = 0;

        foreach ($brackets as $bracket) {
            $min = $bracket['min'] ?? 0;
            $max = $bracket['max'] ?? PHP_FLOAT_MAX;
            $rate = $bracket['rate'] ?? 0;

            if ($monthlySalary > $min) {
                $taxable = min($monthlySalary, $max) - max($min, $previousLimit);
                $tax += $taxable * ($rate / 100);
            }
            $previousLimit = $max;
        }

        return round($tax, 2);
    }

    private function getTaxBrackets()
    {
        $raw = DB::table('settings')->where('key', 'tax-brackets')->value('value');
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        // backward compatibility: unwrap {brackets: [...]} from old saves
        if (isset($data['brackets']) && is_array($data['brackets'])) {
            $data = $data['brackets'];
        }
        return $data;
    }

    private function getAllowanceName($type)
    {
        $type = str_replace('allowance_', '', $type);
        
        $names = [
            'transport' => 'بدل نقل',
            'housing' => 'بدل سكن',
            'food' => 'بدل طعام',
            'phone' => 'بدل هاتف',
            'education' => 'بدل تعليم',
            'medical' => 'بدل علاج',
            'other' => 'بدل أخرى',
            'custom' => 'بدل مخصص',
        ];
        return $names[$type] ?? 'بدل ' . $type;
    }

    private function getIncentiveName($type)
    {
        $names = [
            'bonus' => 'مكافأة',
            'commission' => 'عمولة',
            'performance' => 'حافز أداء',
            'allowance' => 'بدل إضافي',
            'other' => 'مكافأة أخرى',
            'custom' => 'حافز مخصص',
        ];
        return $names[$type] ?? $type;
    }

    public function employeeDetailedReport(Request $request)
    {
        if ($error = $this->checkPermission($request, 'reports.employee')) {
            return $error;
        }
        
        $employeeId = $request->input('employee_id');
        
        if (!$employeeId) {
            return response()->json(['error' => 'Employee ID is required'], 400);
        }

        $employee = Employee::with([
            'department',
            'compensations',
            'incentives',
            'leaves' => function($q) {
                $q->orderBy('from_date', 'desc');
            },
            'warningsRelation' => function($q) {
                $q->orderBy('date', 'desc');
            },
            'advances' => function($q) {
                $q->orderBy('date', 'desc');
            },
            'assets' => function($q) {
                $q->orderBy('created_at', 'desc');
            },
            'deductions',
            'fingerprints.device',
        ])->find($employeeId);

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Calculate leave summary by type
        $leaveTypes = $employee->leaves->groupBy('type');
        $leavesSummaryByType = [];
        foreach ($leaveTypes as $type => $items) {
            $leavesSummaryByType[$type] = [
                'count' => $items->count(),
                'total_days' => $items->sum('days'),
                'approved_count' => $items->where('status', 'approved')->count(),
                'pending_count' => $items->where('status', 'pending')->count(),
                'rejected_count' => $items->where('status', 'rejected')->count(),
                'paid_days' => $items->where('paid', true)->sum('days'),
                'unpaid_days' => $items->where('paid', false)->sum('days'),
            ];
        }

        // Calculate warnings summary by type
        $warningsByType = $employee->warningsRelation->groupBy('type');
        $warningsSummaryByType = [];
        foreach ($warningsByType as $type => $items) {
            $warningsSummaryByType[$type] = [
                'count' => $items->count(),
                'by_status' => $items->groupBy('status')->map(fn($i) => $i->count())->toArray(),
            ];
        }

        // Calculate advances summary
        $advancesApproved = $employee->advances->where('status', 'approved');
        $advancesTotalPaid = $advancesApproved->sum(function($a) {
            return ($a->paid_installments ?? 0) * ($a->monthly_installment ?? 0);
        });

        // Get attendance records for this employee
        $attendanceRecords = \App\Models\AttendanceRecord::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get();
        
        $data = [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_number' => $employee->employee_number ?? $employee->file_number ?? '-',
                'id_number' => $employee->id_number ?? '-',
                'email' => $employee->email ?? '-',
                'phone' => $employee->phone ?? '-',
                'address' => $employee->address ?? '-',
                'birth_date' => $employee->birth_date ?? '-',
                'gender' => $employee->gender ?? '-',
                'marital_status' => $employee->marital_status ?? '-',
                'department' => $employee->department?->name ?? '-',
                'position' => $employee->position ?? '-',
                'hire_date' => $employee->hire_date ?? '-',
                'contract_type' => $employee->contract_type ?? '-',
                'employment_status' => $employee->employment_status ?? '-',
                'base_salary' => $employee->base_salary ?? 0,
                'position_allowance' => $employee->position_allowance ?? 0,
                'bank_name' => $employee->bank_name ?? '-',
                'bank_account' => $employee->bank_account ?? '-',
                'insurance_type' => $employee->insurance_type ?? 'none',
                'insurance_amount' => $employee->insurance_amount ?? 0,
                'status' => $employee->status ?? '-',
                'image_url' => $employee->image ? asset('storage/' . $employee->image) : null,
                'cv_url' => $employee->cv ? asset('storage/' . $employee->cv) : null,
                'cv_filename' => $employee->cv ? basename($employee->cv) : null,
            ],
            'salary_info' => [
                'base_salary' => (float) ($employee->base_salary ?? 0),
                'position_allowance' => (float) ($employee->position_allowance ?? 0),
                'allowances' => $employee->compensations->map(function($c) {
                    $type = $c->type ?? '';
                    $isCustom = str_contains($type, 'custom') || ($c->note && !in_array($c->note, ['transport', 'housing', 'food', 'phone', 'education', 'medical', 'other']));
                    return [
                        'id' => $c->id,
                        'type' => $type,
                        'name' => $isCustom && $c->note ? $c->note : $this->getAllowanceName($type),
                        'amount' => (float) $c->value,
                        'date' => $c->date,
                        'notes' => $c->note ?? '-',
                    ];
                })->toArray(),
                'total_allowances' => (float) $employee->compensations->sum('value'),
                'incentives' => $employee->incentives ? $employee->incentives->map(function($i) {
                    $type = $i->type ?? '';
                    $isCustom = $type === 'custom' || ($i->note && !in_array($i->note, ['bonus', 'commission', 'performance', 'allowance', 'other']));
                    return [
                        'id' => $i->id,
                        'type' => $type,
                        'name' => $isCustom && $i->note ? $i->note : $this->getIncentiveName($type),
                        'amount' => (float) $i->value,
                        'date' => $i->date,
                        'notes' => $i->note ?? '-',
                    ];
                })->toArray() : [],
                'total_incentives' => $employee->incentives ? (float) $employee->incentives->sum('value') : 0,
                'deductions' => $employee->deductions ? $employee->deductions->map(fn($d) => [
                    'id' => $d->id,
                    'type' => $d->type ?? '-',
                    'amount' => (float) ($d->amount ?? 0),
                    'date' => $d->date ?? '-',
                    'reason' => $d->reason ?? '-',
                ])->toArray() : [],
                'total_deductions' => $employee->deductions ? (float) $employee->deductions->sum('amount') : 0,
                'insurance_type' => $employee->insurance_type ?? 'none',
                'insurance_amount' => (float) ($employee->insurance_amount ?? 0),
                'bank_name' => $employee->bank_name ?? '-',
                'bank_account' => $employee->bank_account ?? '-',
            ],
            'leaves_summary' => [
                'total_count' => $employee->leaves->count(),
                'total_days' => $employee->leaves->sum('days'),
                'by_type' => $leavesSummaryByType,
                'approved_days' => $employee->leaves->where('status', 'approved')->sum('days'),
                'pending_days' => $employee->leaves->where('status', 'pending')->sum('days'),
                'rejected_days' => $employee->leaves->where('status', 'rejected')->sum('days'),
                'paid_days' => $employee->leaves->where('paid', true)->sum('days'),
                'unpaid_days' => $employee->leaves->where('paid', false)->sum('days'),
            ],
            'leaves' => $employee->leaves->map(fn($l) => [
                'id' => $l->id,
                'type' => $l->type ?? '-',
                'from_date' => $l->from_date,
                'to_date' => $l->to_date,
                'days' => $l->days ?? 0,
                'status' => $l->status ?? '-',
                'reason' => $l->reason ?? '-',
                'paid' => $l->paid ? 'نعم' : 'لا',
                'notes' => $l->notes ?? '-',
                'created_at' => $l->created_at,
                'approved_by' => $l->approved_by ?? '-',
                'approved_at' => $l->approved_at ?? '-',
            ])->toArray(),
            'warnings_summary' => [
                'total_count' => $employee->warningsRelation->count(),
                'by_type' => $warningsSummaryByType,
                'active_count' => $employee->warningsRelation->where('status', 'active')->count(),
                'resolved_count' => $employee->warningsRelation->where('status', 'resolved')->count(),
            ],
            'warnings' => $employee->warningsRelation->map(fn($w) => [
                'id' => $w->id,
                'type' => $w->type ?? '-',
                'reason' => $w->reason ?? '-',
                'date' => $w->date,
                'status' => $w->status ?? '-',
                'notes' => $w->notes ?? '-',
                'created_by' => $w->created_by ?? '-',
                'created_at' => $w->created_at,
            ])->toArray(),
            'advances_summary' => [
                'total_count' => $employee->advances->count(),
                'total_amount' => (float) $employee->advances->sum('amount'),
                'pending_count' => $employee->advances->where('status', 'pending')->count(),
                'pending_amount' => (float) $employee->advances->where('status', 'pending')->sum('amount'),
                'approved_count' => $employee->advances->where('status', 'approved')->count(),
                'approved_amount' => (float) $employee->advances->where('status', 'approved')->sum('amount'),
                'rejected_count' => $employee->advances->where('status', 'rejected')->count(),
                'rejected_amount' => (float) $employee->advances->where('status', 'rejected')->sum('amount'),
                'total_paid' => $advancesTotalPaid,
                'total_remaining' => (float) $employee->advances->sum('remaining_amount'),
            ],
            'advances' => $employee->advances->map(fn($a) => [
                'id' => $a->id,
                'amount' => (float) $a->amount,
                'type' => $a->type ?? 'short',
                'reason' => $a->reason ?? '-',
                'date' => $a->date,
                'status' => $a->status ?? '-',
                'installment_count' => $a->installment_count ?? 0,
                'monthly_installment' => (float) ($a->monthly_installment ?? 0),
                'paid_installments' => $a->paid_installments ?? 0,
                'remaining_amount' => (float) ($a->remaining_amount ?? 0),
                'total_paid' => ($a->paid_installments ?? 0) * ($a->monthly_installment ?? 0),
                'note' => $a->note ?? '',
                'attachment' => $a->attachment,
                'attachment_url' => $a->attachment ? url('storage/' . $a->attachment) : null,
                'approved_by' => $a->approved_by ?? '-',
                'approved_at' => $a->approved_at ?? '-',
                'created_at' => $a->created_at,
            ])->toArray(),
            'assets' => [
                'total_count' => $employee->assets->count(),
                'items' => $employee->assets->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name ?? '-',
                    'asset_number' => $a->asset_number ?? '-',
                    'serial_number' => $a->serial_number ?? '-',
                    'condition' => $a->condition ?? '-',
                    'received_date' => $a->received_date ?? '-',
                    'returned_date' => $a->returned_date ?? '-',
                    'status' => $a->status ?? '-',
                    'notes' => $a->notes ?? '-',
                ])->toArray(),
            ],
            'fingerprints' => [
                'total_count' => $employee->fingerprints->count(),
                'items' => $employee->fingerprints->map(fn($f) => [
                    'id' => $f->id,
                    'finger_id' => $f->finger_id ?? '-',
                    'finger_position' => $f->finger_position ?? '-',
                    'finger' => $f->finger ?? '-',
                    'is_active' => $f->is_active ?? true,
                    'device_name' => $f->device?->name ?? '-',
                    'created_at' => $f->created_at ?? '-',
                ])->toArray(),
            ],
            'salary_history' => [],
            'attendance_summary' => [
                'working_days' => $attendanceRecords->count(),
                'on_time_days' => $attendanceRecords->where('check_in_type', 'on_time')->count(),
                'late_days' => $attendanceRecords->where('check_in_type', 'late')->count(),
                'early_days' => $attendanceRecords->where('check_in_type', 'early')->count(),
                'absent_days' => $attendanceRecords->where('is_absent', true)->count(),
                'total_deduction' => (float) $attendanceRecords->sum('total_deduction'),
                'total_delay_minutes' => $attendanceRecords->sum('check_in_delay_minutes'),
            ],
            'attendance' => $attendanceRecords->map(fn($a) => [
                'id' => $a->id,
                'date' => $a->date,
                'check_in_time' => $a->check_in_time,
                'check_in_type' => $a->check_in_type,
                'check_in_delay_minutes' => $a->check_in_delay_minutes,
                'check_out_time' => $a->check_out_time,
                'check_out_type' => $a->check_out_type,
                'worked_hours' => $a->worked_hours,
                'expected_hours' => $a->expected_hours,
                'has_delay' => $a->has_delay,
                'is_absent' => $a->is_absent,
                'delay_deduction' => (float) $a->delay_deduction,
                'early_leave_deduction' => (float) $a->early_leave_deduction,
                'absence_deduction' => (float) $a->absence_deduction,
                'total_deduction' => (float) $a->total_deduction,
                'delay_excused' => $a->delay_excused,
                'absence_excused' => $a->absence_excused,
                'warning_issued' => $a->warning_issued,
            ])->toArray(),
            'evaluations' => [],
            'documents' => [],
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        return response()->json($data);
    }
}
