<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'bank_name',
        'total_amount',
        'employee_count',
        'file_path',
        'status',
        'processed_at',
        'employee_ids',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'employee_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getBankNameArabic($bankName)
    {
        return match($bankName) {
            'فهد' => 'بنك الفهد الإسلامي',
            'التعاون' => 'بنك التعاون',
            'الزراعي' => 'البنك الزراعي',
            'الشعب' => 'بنك الشعب',
            'الثقة' => 'بنك الثقة',
            'الخرطوم' => 'بنك الخرطوم',
            'فيصل' => 'بنك فيصل الإسلامي',
            'السودان' => 'بنك السودان',
            'طيبة' => 'بنك طيبة الإسلامي',
            'الدوحة' => 'بنك الدوحة',
            'التأمين' => 'شركة التأمين',
            'اخرى' => 'بنوك أخرى',
            default => $bankName,
        };
    }

    public static function generateForBank($month, $year, $bankName = 'فهد', $employeeIds = null)
    {
        $query = Employee::where('status', 'active')
            ->where('bank_name', $bankName)
            ->whereNotNull('bank_account')
            ->where('bank_account', '!=', '');

        if ($employeeIds && is_array($employeeIds) && count($employeeIds) > 0) {
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();

        $totalAmount = 0;
        $records = [];
        $employeeIdList = [];

        foreach ($employees as $employee) {
            $baseSalary = (float) ($employee->base_salary ?? 0);
            $positionAllowance = (float) ($employee->position_allowance ?? 0);
            
            // Get allowances and incentives from incentives table
            $allowancesTotal = $employee->incentives()
                ->where('type', 'like', 'allowance_%')
                ->sum('value');
            $incentivesTotal = $employee->incentives()
                ->where('type', 'not like', 'allowance_%')
                ->sum('value');
            
            // Insurance deduction
            $insuranceAmount = (float) ($employee->insurance_amount ?? 0);
            
            // Calculate net salary
            $grossSalary = $baseSalary + $positionAllowance + $allowancesTotal + $incentivesTotal;
            $netSalary = $grossSalary - $insuranceAmount;

            $records[] = [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'file_number' => $employee->file_number,
                'bank_account' => $employee->bank_account,
                'bank_branch' => $employee->bank_branch ?? '',
                'base_salary' => $baseSalary,
                'position_allowance' => $positionAllowance,
                'allowances' => $allowancesTotal,
                'incentives' => $incentivesTotal,
                'insurance' => $insuranceAmount,
                'amount' => $netSalary,
            ];

            $totalAmount += $netSalary;
            $employeeIdList[] = $employee->id;
        }

        $export = self::create([
            'user_id' => auth()->id(),
            'month' => $month,
            'year' => $year,
            'bank_name' => $bankName,
            'total_amount' => $totalAmount,
            'employee_count' => count($records),
            'employee_ids' => $employeeIdList,
            'status' => 'pending',
        ]);

        return [
            'export' => $export,
            'records' => $records,
        ];
    }
}
