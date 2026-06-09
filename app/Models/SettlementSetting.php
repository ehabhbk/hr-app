<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function updateValue($key, $value)
    {
        $setting = self::firstOrCreate(['key' => $key]);
        $setting->update(['value' => $value]);
        return $setting;
    }

    public static function getAllSettings()
    {
        return self::all()->pluck('value', 'key');
    }

    public static function calculateEndOfServiceBonus($baseSalary, $yearsOfService)
    {
        $bonusSettings = self::getValue('service_end_bonus', []);
        
        if (!($bonusSettings['enabled'] ?? false)) {
            return 0;
        }

        $monthsPerYear = $bonusSettings['months_per_year'] ?? 0;
        $maxMonths = $bonusSettings['max_months'] ?? 0;
        
        $totalMonths = $yearsOfService * 12;
        
        if ($maxMonths > 0) {
            $totalMonths = min($totalMonths, $maxMonths);
        }
        
        $bonusMonths = min($totalMonths, $monthsPerYear * $yearsOfService);
        
        return $baseSalary * ($bonusMonths / 12);
    }

    public static function calculateSeverancePay($baseSalary, $yearsOfService, $monthsOfService)
    {
        $settings = self::getValue('severance_pay', []);
        
        if (!($settings['enabled'] ?? false)) {
            return 0;
        }

        $first5Years = $settings['first_5_years_months'] ?? 1;
        $after5Years = $settings['after_5_years_months'] ?? 2;
        $maxYears = $settings['max_years'] ?? 12;

        $totalMonths = $yearsOfService * 12 + $monthsOfService;
        
        $limitedMonths = min($totalMonths, $maxYears * 12);
        
        $bonusMonths = 0;
        
        if ($limitedMonths <= 60) {
            $bonusMonths = ($limitedMonths / 12) * $first5Years;
        } else {
            $bonusMonths = 5 * $first5Years;
            $remainingMonths = $limitedMonths - 60;
            $bonusMonths += ($remainingMonths / 12) * $after5Years;
        }

        return ($baseSalary / 12) * $bonusMonths;
    }

    public static function calculateNoticePeriod($yearsOfService)
    {
        $settings = self::getValue('notice_period', []);
        
        if (!($settings['enabled'] ?? false)) {
            return $settings['min_days'] ?? 30;
        }

        $byYears = $settings['by_service_years'] ?? [];
        
        if ($yearsOfService >= 10) {
            return $byYears['10+'] ?? 90;
        } elseif ($yearsOfService >= 5) {
            return $byYears['5-10'] ?? 60;
        }
        
        return $byYears['0-5'] ?? 30;
    }

    public static function calculateLeaveEncashment($baseSalary, $days, $yearsOfService)
    {
        $settings = self::getValue('annual_leave_encashment', []);
        
        if (!($settings['enabled'] ?? false)) {
            return 0;
        }

        $maxDays = $settings['max_days_per_year'] ?? 0;
        $minMonths = $settings['min_service_months'] ?? 0;

        if ($yearsOfService * 12 < $minMonths) {
            return 0;
        }

        $daysToEncash = $days;
        
        if ($maxDays > 0) {
            $daysToEncash = min($days, $maxDays);
        }

        $dailyRate = $baseSalary / 30;
        
        return $dailyRate * $daysToEncash;
    }

    public static function calculateFullSettlement($employee)
    {
        $baseSalary = (float) ($employee->base_salary ?? 0);
        $positionAllowance = (float) ($employee->position_allowance ?? 0);
        $grossSalary = $baseSalary + $positionAllowance;
        
        $hireDate = $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date) : now();
        $serviceEndDate = now();
        
        $totalMonths = $hireDate->diffInMonths($serviceEndDate);
        $yearsOfService = floor($totalMonths / 12);
        $remainingMonths = $totalMonths % 12;
        
        $leaves = $employee->leaves ?? collect();
        $pendingLeaves = $leaves->where('status', 'approved')->where('paid', false)->sum('days');
        
        $settlement = [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'hire_date' => $hireDate->format('Y-m-d'),
            'service_end_date' => $serviceEndDate->format('Y-m-d'),
            'years_of_service' => $yearsOfService,
            'months_of_service' => $remainingMonths,
            'total_months' => $totalMonths,
            
            // Basic salary info
            'base_salary' => $baseSalary,
            'position_allowance' => $positionAllowance,
            'gross_salary' => $grossSalary,
            'daily_rate' => $grossSalary / 30,
            'monthly_rate' => $grossSalary,
            
            // Severance pay (مكافأة إنهاء الخدمة)
            'severance_pay' => self::calculateSeverancePay($grossSalary, $yearsOfService, $remainingMonths),
            
            // Notice period compensation (تعويض فترة الإخطار)
            'notice_period_days' => self::calculateNoticePeriod($yearsOfService),
            'notice_period_amount' => self::calculateNoticePeriod($yearsOfService) * ($grossSalary / 30),
            
            // Unused leaves encashment (استبدال الإجازات)
            'unused_leave_days' => $pendingLeaves,
            'unused_leave_amount' => self::calculateLeaveEncashment($grossSalary, $pendingLeaves, $yearsOfService),
            
            // Other allowances
            'transport_allowance' => (float) ($employee->transport_allowance ?? 0),
            'housing_allowance' => (float) ($employee->housing_allowance ?? 0),
            'food_allowance' => (float) ($employee->food_allowance ?? 0),
            
            // Advances to deduct
            'remaining_advances' => $employee->advances ? $employee->advances->where('status', 'approved')->sum('remaining_amount') : 0,
            
            // Summary
            'total_due' => 0,
            'total_deduct' => 0,
            'net_settlement' => 0,
        ];
        
        // Calculate totals
        $settlement['total_due'] = 
            $settlement['severance_pay'] + 
            $settlement['notice_period_amount'] + 
            $settlement['unused_leave_amount'] +
            $settlement['transport_allowance'] +
            $settlement['housing_allowance'] +
            $settlement['food_allowance'];
        
        $settlement['total_deduct'] = $settlement['remaining_advances'];
        
        $settlement['net_settlement'] = $settlement['total_due'] - $settlement['total_deduct'];
        
        return $settlement;
    }
}
