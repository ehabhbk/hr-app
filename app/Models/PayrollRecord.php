<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PayrollRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'base_salary',
        'total_allowances',
        'total_incentives',
        'total_deductions',
        'total_loan_payments',
        'gross_salary',
        'income_tax',
        'net_salary',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_incentives' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_loan_payments' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public static function generateForMonth($month, $year)
    {
        $employees = Employee::where('status', 'active')->get();
        $records = [];

        foreach ($employees as $employee) {
            $allIncentives = $employee->incentives()->get();

            // Fixed allowances (recurring) — always included, no month filter
            $fixedAllowances = $allIncentives->where('is_recurring', true);
            $totalAllowances = (float) $fixedAllowances->sum('value');

            // One-time button incentives — current month only
            $oneTimeIncentives = $allIncentives->filter(function($item) use ($month, $year) {
                return !$item->is_recurring
                    && $item->date
                    && date('m', strtotime($item->date)) == str_pad($month, 2, '0', STR_PAD_LEFT)
                    && date('Y', strtotime($item->date)) == $year;
            });
            $totalIncentives = (float) $oneTimeIncentives->sum('value');

            $deductions = $employee->deductions()
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->sum('amount');
            $loanPayments = $employee->loanPayments()
                ->whereMonth('payment_date', $month)
                ->whereYear('payment_date', $year)
                ->sum('amount');

            $baseSalary = $employee->base_salary ?? 0;
            $grossSalary = $baseSalary + $totalAllowances + $totalIncentives;
            $totalDeductions = $deductions + $loanPayments;
            $netSalary = $grossSalary - $totalDeductions;
            
            $raw = DB::table('settings')->where('key', 'tax-brackets')->value('value');
            $brackets = null;
            if ($raw !== null) {
                if (is_array($raw)) {
                    $data = $raw;
                } else {
                    $decoded = json_decode($raw, true);
                    $data = is_array($decoded) ? $decoded : null;
                }
                // backward compatibility: unwrap {brackets: [...]} from old saves
                if (is_array($data) && isset($data['brackets'])) {
                    $brackets = $data['brackets'];
                } else {
                    $brackets = $data;
                }
            }
            $tax = self::calculateIncomeTax($baseSalary, $brackets);
            $netSalary -= $tax;

            $records[] = self::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $month, 'year' => $year],
                [
                    'base_salary' => $baseSalary,
                    'total_allowances' => $totalAllowances,
                    'total_incentives' => $totalIncentives,
                    'total_deductions' => $deductions,
                    'total_loan_payments' => $loanPayments,
                    'gross_salary' => $grossSalary,
                    'income_tax' => $tax,
                    'net_salary' => $netSalary,
                    'status' => 'calculated',
                ]
            );
        }

        return $records;
    }

    private static function calculateIncomeTax($monthlySalary, $taxBrackets = null)
    {
        $brackets = is_array($taxBrackets) ? $taxBrackets : [];
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
}
