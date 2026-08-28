<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use TCPDF;

trait PdfHelpersTrait
{
    protected function getOrganizationData()
    {
        $org = Setting::where('key', 'organization')->first();
        return $org ? $org->value : [
            'name' => 'Jawda HR',
            'address' => '',
            'phone' => '',
            'email' => '',
            'tax_number' => '',
            'logo' => null,
            'stamp' => null,
        ];
    }

    protected function getLogoHtml($org, $height = 60)
    {
        $logoHtml = '';
        if (!empty($org['logo'])) {
            $logoPath = public_path('storage/' . $org['logo']);
            if ($logoPath && file_exists($logoPath) && is_readable($logoPath)) {
                $logoBase64 = @base64_encode(@file_get_contents($logoPath));
                if ($logoBase64) {
                    $logoExt = pathinfo($logoPath, PATHINFO_EXTENSION);
                    $logoExt = strtolower($logoExt);
                    $logoMime = ($logoExt === 'jpg' || $logoExt === 'jpeg') ? 'image/jpeg' : 'image/png';
                    $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoBase64 . '" style="height:' . $height . 'px;width:' . $height . 'px;object-fit:contain;">';
                }
            }
        }
        return $logoHtml;
    }

    protected function getStampHtml($org, $height = 70)
    {
        $stampHtml = '';
        if (!empty($org['stamp'])) {
            $stampPath = public_path('storage/' . $org['stamp']);
            if ($stampPath && file_exists($stampPath) && is_readable($stampPath)) {
                $stampBase64 = @base64_encode(@file_get_contents($stampPath));
                if ($stampBase64) {
                    $stampExt = pathinfo($stampPath, PATHINFO_EXTENSION);
                    $stampExt = strtolower($stampExt);
                    $stampMime = ($stampExt === 'jpg' || $stampExt === 'jpeg') ? 'image/jpeg' : 'image/png';
                    $stampHtml = '<img src="data:' . $stampMime . ';base64,' . $stampBase64 . '" style="height:' . $height . 'px;width:' . $height . 'px;object-fit:contain;opacity:0.85;">';
                }
            }
        }
        return $stampHtml;
    }

    protected function getOfficialHeaderHtml($org)
    {
        $logoHtml = $this->getLogoHtml($org, 55);
        $orgName = htmlspecialchars($org['name'] ?? 'Jawda HR');
        $orgAddress = htmlspecialchars($org['address'] ?? '');
        $orgPhone = htmlspecialchars($org['phone'] ?? '');
        $orgEmail = htmlspecialchars($org['email'] ?? '');
        $taxNumber = htmlspecialchars($org['tax_number'] ?? '');

        $logoPlaceholder = '<div style="width:55px;height:55px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:9px;font-weight:bold;">شعار</div>';

        $infoParts = array_filter([
            $orgAddress ? 'العنوان: ' . $orgAddress : null,
            $orgPhone ? 'هاتف: ' . $orgPhone : null,
            $orgEmail ? 'بريد: ' . $orgEmail : null,
        ]);

        return '
        <table style="width:100%;border:none;margin-bottom:12px;border-collapse:collapse;">
            <tr>
                <td style="width:65px;text-align:center;border:none;vertical-align:middle;padding:5px;">
                    ' . ($logoHtml ?: $logoPlaceholder) . '
                </td>
                <td style="border:none;padding:5px 10px;vertical-align:middle;">
                    <table style="width:100%;border:none;border-collapse:collapse;">
                        <tr>
                            <td style="text-align:center;border:none;">
                                <h1 style="font-size:18px;margin:0;color:#1e3a5f;font-weight:bold;letter-spacing:0.5px;">' . $orgName . '</h1>
                            </td>
                        </tr>
                        ' . (!empty($infoParts) ? '
                        <tr>
                            <td style="text-align:center;border:none;">
                                <p style="font-size:9px;color:#64748b;margin:3px 0 0 0;">' . implode(' &nbsp;|&nbsp; ', $infoParts) . '</p>
                            </td>
                        </tr>' : '') . '
                        ' . ($taxNumber ? '
                        <tr>
                            <td style="text-align:center;border:none;">
                                <p style="font-size:8px;color:#94a3b8;margin:2px 0 0 0;">الرقم الضريبي: ' . $taxNumber . '</p>
                            </td>
                        </tr>' : '') . '
                    </table>
                </td>
                <td style="width:65px;border:none;"></td>
            </tr>
        </table>
        <div style="height:3px;background:linear-gradient(90deg,#1e3a5f,#3b82f6,#6366f1,#3b82f6,#1e3a5f);margin:8px 0 15px 0;border-radius:2px;"></div>';
    }

    protected function getOfficialFooterHtml($org)
    {
        $stampHtml = $this->getStampHtml($org, 60);

        $stampPlaceholder = '<div style="width:55px;height:55px;background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div>';

        return '
        <div style="margin-top:25px;page-break-inside:avoid;">
            <div style="height:1.5px;background:linear-gradient(90deg,transparent,#cbd5e1,transparent);margin:12px 0;"></div>
            <table style="width:100%;border:none;border-collapse:collapse;">
                <tr>
                    <td style="width:30%;text-align:center;border:none;vertical-align:top;padding:5px;">
                        <div style="min-height:65px;display:flex;align-items:center;justify-content:center;">
                            ' . ($stampHtml ?: $stampPlaceholder) . '
                        </div>
                        <p style="font-size:8px;color:#64748b;margin:4px 0 0 0;">ختم المؤسسة</p>
                    </td>
                    <td style="width:40%;text-align:center;border:none;vertical-align:top;padding:5px;">
                        <div style="border-bottom:1.5px solid #1e3a5f;width:140px;height:35px;margin:0 auto;"></div>
                        <p style="font-size:9px;color:#1e3a5f;margin:5px 0 0 0;font-weight:bold;">مدير الموارد البشرية</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التاريخ: ....................</p>
                    </td>
                    <td style="width:30%;text-align:center;border:none;vertical-align:top;padding:5px;">
                        <div style="border-bottom:1.5px solid #059669;width:140px;height:35px;margin:0 auto;"></div>
                        <p style="font-size:9px;color:#059669;margin:5px 0 0 0;font-weight:bold;">المدير العام</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                        <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التاريخ: ....................</p>
                    </td>
                </tr>
            </table>
            <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:6px 12px;margin-top:12px;text-align:center;">
                <p style="font-size:7px;color:#64748b;margin:0;">
                    <strong>Jawda HR</strong> — نظام إدارة الموارد البشرية | تاريخ الطباعة: ' . now()->format('Y-m-d H:i') . '
                </p>
            </div>
        </div>';
    }

    protected function getAllowanceTypeName($type)
    {
        $type = strtolower($type);
        $type = str_replace(['_allowance', '_'], '', $type);
        
        $names = [
            'transport' => 'بدل نقل',
            'housing' => 'بدل سكن',
            'food' => 'بدل طعام',
            'phone' => 'بدل هاتف',
            'education' => 'بدل تعليم',
            'medical' => 'بدل علاج',
            'other' => 'بدل أخرى',
            't' => 'بدل نقل',
            'h' => 'بدل سكن',
            'f' => 'بدل طعام',
            'p' => 'بدل هاتف',
            'e' => 'بدل تعليم',
            'm' => 'بدل علاج',
        ];
        return $names[$type] ?? 'بدل ' . $type;
    }

    protected function getIncentiveTypeName($type)
    {
        $type = strtolower($type);
        $type = str_replace(['_incentive', '_'], '', $type);
        
        $names = [
            'bonus' => 'مكافأة',
            'commission' => 'عمولة',
            'performance' => 'حافز اداء',
            'allowance' => 'بدل اضافي',
            'other' => 'مكافأة اخرى',
            'b' => 'مكافأة',
            'c' => 'عمولة',
            'perf' => 'حافز اداء',
        ];
        return $names[$type] ?? $type;
    }

    protected function getInsuranceTypeName($type)
    {
        $names = [
            'none' => 'بدون',
            'health' => 'صحي',
            'social' => 'اجتماعي',
            'both' => 'صحي واجتماعي',
        ];
        return $names[$type] ?? $type;
    }

    protected function getDefaultTaxBrackets()
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
}

class PdfExportController extends Controller
{
    use PdfHelpersTrait;

    public function __construct()
    {
        if (class_exists('\TCPDF')) {
            @ini_set('gd.jpeg_ignore_warning', 1);
        }
    }

    public function salaryReport(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $departmentId = $request->input('department_id');
            
            $query = Employee::with([
                'department', 
                'compensations', 
                'incentives',
                'deductions',
                'advances' => function($q) {
                    $q->where('status', 'approved')->where('remaining_amount', '>', 0);
                },
                'attendanceRecords' => function($q) use ($month, $year) {
                    $q->whereMonth('date', $month)->whereYear('date', $year);
                }
            ])->where('status', 'active');
            
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }
            
            $employees = $query->get();
            $org = $this->getOrganizationData();
            $monthName = Carbon::create($year, $month, 1)->locale('ar')->monthName;
            $currencySymbol = $org['currency_symbol'] ?? 'جنيه سوداني';
            
            $salaryData = [];
            foreach ($employees as $emp) {
                $baseSalary = (float) ($emp->base_salary ?? 0);
                $positionAllowance = (float) ($emp->position_allowance ?? 0);
                
                $allowancesList = [];
                $totalAllowances = 0;
                foreach ($emp->compensations as $comp) {
                    $typeName = $this->getAllowanceTypeName($comp->type ?? 'other');
                    $amount = (float) $comp->value;
                    $totalAllowances += $amount;
                    $allowancesList[] = ['name' => $typeName, 'amount' => $amount];
                }
                
                $incentivesList = [];
                $totalIncentives = 0;
                foreach ($emp->incentives ?? [] as $inc) {
                    $typeName = $this->getIncentiveTypeName($inc->type);
                    $amount = (float) $inc->value;
                    $totalIncentives += $amount;
                    $incentivesList[] = ['name' => $typeName, 'amount' => $amount];
                }
                
                $grossSalary = $baseSalary + $positionAllowance + $totalAllowances + $totalIncentives;
                
                $insuranceType = $emp->insurance_type ?? 'none';
                $insuranceAmount = (float) ($emp->insurance_amount ?? 0);
                
                $otherDeductions = (float) $emp->deductions->where('is_active', true)->sum('amount');
                
                $attendanceDeductions = 0;
                if (isset($emp->attendanceRecords)) {
                    foreach ($emp->attendanceRecords as $record) {
                        if (($record->total_deduction ?? 0) > 0) {
                            $attendanceDeductions += (float) $record->total_deduction;
                        }
                    }
                }
                
                $totalAdvanceDeduction = 0;
                foreach ($emp->advances as $advance) {
                    if ($advance->remaining_amount <= 0) continue;
                    $remainingAmount = (float) $advance->remaining_amount;
                    $monthlyInstallment = (float) ($advance->monthly_installment ?? 0);
                    $isLongTerm = isset($advance->advance_type) && $advance->advance_type === 'long_term';
                    if (!isset($advance->advance_type)) {
                        $isLongTerm = ($advance->installment_count ?? 1) > 1;
                    }
                    if ($isLongTerm) {
                        $totalAdvanceDeduction += min($monthlyInstallment, $remainingAmount);
                    } else {
                        $totalAdvanceDeduction += $remainingAmount;
                    }
                }
                
                $totalAllDeductions = $insuranceAmount + $otherDeductions + $attendanceDeductions + $totalAdvanceDeduction;
                $netSalary = $grossSalary - $totalAllDeductions;
                
                $salaryData[] = [
                    'employee' => $emp,
                    'base_salary' => $baseSalary,
                    'position_allowance' => $positionAllowance,
                    'allowances' => $allowancesList,
                    'total_allowances' => $totalAllowances,
                    'incentives' => $incentivesList,
                    'total_incentives' => $totalIncentives,
                    'gross_salary' => $grossSalary,
                    'insurance_name' => $this->getInsuranceTypeName($insuranceType),
                    'insurance_amount' => $insuranceAmount,
                    'deductions' => $otherDeductions,
                    'attendance_deductions' => $attendanceDeductions,
                    'advance_deductions' => $totalAdvanceDeduction,
                    'net_salary' => $netSalary,
                ];
            }
            
            ob_start();
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('كشف المرتبات');
            $pdf->SetSubject('كشف المرتبات الشهري');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 25);
            
            $pdf->AddPage();
            
            $html = $this->generateDetailedSalaryReportHtml($org, $salaryData, $monthName, $year, $currencySymbol);
            ob_start();
            $pdf->writeHTML($html, true, false, true, false, 'R');
            ob_end_clean();
            
            $pdfContent = $pdf->Output('salary_report.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="salary_report_' . $monthName . '_' . $year . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    public function testCors()
    {
        return response()->json(['message' => 'CORS test OK'])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }

    public function incomeTaxReport(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $departmentId = $request->input('department_id');
            
            $query = Employee::with(['department', 'compensations'])->where('status', 'active');
            
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }
            
            $employees = $query->get();
            $org = $this->getOrganizationData();
            
            $taxData = [];
            foreach ($employees as $emp) {
                $baseSalary = (float) ($emp->base_salary ?? 0);
                $annualSalary = $baseSalary * 12;

                $monthlyTax = $this->calculateIncomeTax($baseSalary);
                $annualTax = $monthlyTax * 12;

                $taxData[] = [
                    'employee' => $emp,
                    'monthly_salary' => $baseSalary,
                    'annual_salary' => $annualSalary,
                    'annual_tax' => $annualTax,
                    'monthly_tax' => $monthlyTax,
                ];
            }

            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();

            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('تقرير ضريبة الدخل');
            $pdf->SetSubject('تقرير ضريبة الدخل السنوي');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 30);
            
            ob_start();
            $pdf->AddPage();
            ob_end_clean();
            
            $html = $this->generateIncomeTaxReportHtml($org, $taxData, $year);
            ob_start();
            $pdf->writeHTML($html, true, false, true, false, 'R');
            ob_end_clean();
            
            $pdfContent = $pdf->Output('income_tax_report.pdf', 'S');

            $resp = response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="income_tax_' . $year . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');

            return $resp;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Income Tax PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500)
                ->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function leaveWarningReport(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $departmentId = $request->input('department_id');
            
            $query = Employee::with(['department', 'leaves', 'warningsRelation'])->where('status', 'active');
            
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }
            
            $employees = $query->get();
            $org = $this->getOrganizationData();
            
            $reportData = [];
            foreach ($employees as $emp) {
                $reportData[] = [
                    'employee' => $emp,
                    'leaves' => $emp->leaves->whereYear('from_date', $year),
                    'warnings' => $emp->warningsRelation->whereYear('date', $year),
                ];
            }
            
            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('Leave and Warning Report');
            $pdf->SetSubject('Annual Leave and Warning Report');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 30);
            
            $pdf->AddPage();
            
            $html = $this->generateLeaveWarningReportHtml($org, $reportData, $year);
            $pdf->writeHTML($html, true, false, true, false, 'R');
            
            $pdfContent = $pdf->Output('leave_warning_report.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="leave_warning_' . $year . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Leave Warning PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    public function letter(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');
            $letterType = $request->input('type', 'termination');
            
            $employee = Employee::with('department')->findOrFail($employeeId);
            $org = $this->getOrganizationData();
            
            $params = $request->all();
            
            $content = $this->generateLetterContent($letterType, $employee, $org, $params);
            
            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle($content['title']);
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 12);
            $pdf->SetAutoPageBreak(true, 40);
            
            $pdf->AddPage();
            
            $html = $this->generateLetterHtml($org, $employee, $content);
            $pdf->writeHTML($html, true, false, true, false, 'R');
            
            $pdfContent = $pdf->Output('letter.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="letter_' . $employee->name . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Letter PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    public function departmentReport(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $org = $this->getOrganizationData();
            
            $departments = Department::with(['employees' => function($q) {
                $q->where('status', 'active');
            }])->get();
            
            $reportData = [];
            foreach ($departments as $dept) {
                $totalSalaries = $dept->employees->sum('base_salary');
                $reportData[] = [
                    'name' => $dept->name,
                    'employee_count' => $dept->employees->count(),
                    'total_salaries' => $totalSalaries,
                ];
            }
            
            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('تقرير الأقسام');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 30);
            
            $pdf->AddPage();
            
            $html = $this->generateDepartmentReportHtml($org, $reportData, $year);
            $pdf->writeHTML($html, true, false, true, false, 'R');
            
            $pdfContent = $pdf->Output('department_report.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="department_' . $year . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Department PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    public function salaryIncreaseReport(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $departmentId = $request->input('department_id');
            $org = $this->getOrganizationData();
            
            $query = Employee::with(['department'])->where('status', 'active');
            
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }
            
            $employees = $query->get();
            
            $reportData = [];
            foreach ($employees as $emp) {
                $baseSalary = (float) ($emp->base_salary ?? 0);
                $increasePercent = 10;
                $increaseAmount = $baseSalary * ($increasePercent / 100);
                
                $reportData[] = [
                    'employee' => $emp,
                    'base_salary' => $baseSalary,
                    'increase_percent' => $increasePercent,
                    'increase_amount' => $increaseAmount,
                    'new_salary' => $baseSalary + $increaseAmount,
                ];
            }
            
            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('Salary Increase Report');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 30);
            
            $pdf->AddPage();
            
            $html = $this->generateSalaryIncreaseReportHtml($org, $reportData, $year);
            $pdf->writeHTML($html, true, false, true, false, 'R');
            
            $pdfContent = $pdf->Output('salary_increase_report.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="salary_increase_' . $year . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Salary Increase PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    public function salaryIncreaseReportOLD(Request $request)
    {
        $year = $request->input('year', now()->year);
        $departmentId = $request->input('department_id');
        $org = $this->getOrganizationData();
        
        $query = Employee::with(['department'])->where('status', 'active');
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        $employees = $query->get();
        
        $reportData = [];
        foreach ($employees as $emp) {
            $baseSalary = (float) ($emp->base_salary ?? 0);
            $increasePercent = 10;
            $increaseAmount = $baseSalary * ($increasePercent / 100);
            
            $reportData[] = [
                'employee' => $emp,
                'base_salary' => $baseSalary,
                'increase_percent' => $increasePercent,
                'increase_amount' => $increaseAmount,
                'new_salary' => $baseSalary + $increaseAmount,
            ];
        }
        
        ob_start();
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        ob_end_clean();
        
        $pdf->SetCreator('Jawda HR');
        $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
        $pdf->SetTitle('Salary Increase Report');
        
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetAutoPageBreak(true, 30);
        
        $pdf->AddPage();
        
        $html = $this->generateSalaryIncreaseReportHtml($org, $reportData, $year);
        $pdf->writeHTML($html, true, false, true, false, 'R');
        
        $pdfContent = $pdf->Output('salary_increase_report.pdf', 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="تقرير_الزيادة_السنوية_' . $year . '.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }

    public function employeeDetailedReport(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');
            
            $employee = Employee::with([
                'department',
                'compensations',
                'incentives',
                'leaves' => function($q) { $q->orderBy('from_date', 'desc'); },
                'warningsRelation' => function($q) { $q->orderBy('date', 'desc'); },
                'advances' => function($q) { $q->orderBy('date', 'desc'); },
                'assets' => function($q) { $q->orderBy('created_at', 'desc'); },
                'deductions',
            ])->findOrFail($employeeId);
            
            $org = $this->getOrganizationData();
            
            ob_start();
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            ob_end_clean();
            
            $pdf->SetCreator('Jawda HR');
            $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
            $pdf->SetTitle('Employee Report - ' . $employee->name);
            $pdf->SetSubject('Employee Detailed Report');
            
            $pdf->setRTL(true);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetAutoPageBreak(true, 25);
            
            $html = $this->generateEmployeeDetailedReportHtml($org, $employee);
            
            $pages = explode('<pagebreak>', $html);
            foreach ($pages as $pageContent) {
                $pdf->AddPage();
                $pdf->writeHTML($pageContent, true, false, true, false, 'R');
            }
            
            $pdfContent = $pdf->Output('employee_report_' . $employee->name . '.pdf', 'S');
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="employee_report_' . $employee->name . '.pdf"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Employee PDF Error: ' . $e->getMessage());
            return response()->json(['error' => 'فشل إنشاء PDF: ' . $e->getMessage()], 500);
        }
    }

    protected function getBaseStyles()
    {
        return '
        <style>
            body { font-family: Amiri, dejavusans, sans-serif; direction: rtl; color: #1e293b; }
            table { width: 100%; border-collapse: collapse; font-size: 9px; }
            th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: center; }
            th { color: #ffffff; font-weight: bold; background: #1e3a5f; font-size: 9px; }
            .title-box { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #1e3a5f; padding: 14px 12px; margin: 15px 0; text-align: center; border-radius: 10px; box-shadow: 0 2px 8px rgba(30,58,95,0.1); }
            .title-box h2 { font-size: 16px; margin: 0; color: #1e3a5f; }
            .title-box p { font-size: 11px; margin: 5px 0 0 0; color: #475569; }
            .summary-box { background: #f8fafc; border: 1.5px solid #cbd5e1; padding: 10px; margin: 10px 0; border-radius: 8px; }
            .section-header { background: #1e3a5f; color: #ffffff; padding: 8px 12px; font-weight: bold; font-size: 11px; text-align: center; margin: 15px 0 8px 0; border-radius: 6px 6px 0 0; }
            .subsection-header { background: #3b82f6; color: #ffffff; padding: 5px 10px; font-weight: bold; font-size: 10px; text-align: center; border-radius: 4px; }
            .row-even { background: #ffffff; }
            .row-odd { background: #f8fafc; }
            .total-row { background: #1e3a5f; color: #ffffff; font-weight: bold; }
            .total-row td { border-color: #334155; }
            .success-bg { background: #dcfce7; color: #16a34a; }
            .warning-bg { background: #fef3c7; color: #d97706; }
            .danger-bg { background: #fee2e2; color: #dc2626; }
            .info-table td { border: none; padding: 3px 5px; font-size: 9px; }
            .text-start { text-align: right; }
            .text-end { text-align: left; }
            .font-bold { font-weight: bold; }
        </style>';
    }

    private function generateSalaryIncreaseReportHtml($org, $data, $year)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . '
        
        ' . $headerHtml . '
        
        <div class="title-box">
            <h2>تقرير الزيادة السنوية</h2>
            <p>السنة: ' . $year . ' | تاريخ الطباعة: ' . now()->format('Y-m-d') . '</p>
        </div>
        
        <table>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:25%">الاسم</th>
                <th style="width:20%">القسم</th>
                <th style="width:12%">الراتب الحالي</th>
                <th style="width:10%">نسبة الزيادة</th>
                <th style="width:13%">مبلغ الزيادة</th>
                <th style="width:15%">الراتب الجديد</th>
            </tr>';
        
        $totalCurrent = 0;
        $totalIncrease = 0;
        $totalNew = 0;
        
        foreach ($data as $i => $row) {
            $emp = $row['employee'];
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $html .= '<tr style="background:' . $bg . '">
                <td>' . ($i + 1) . '</td>
                <td style="font-weight:bold;text-align:right;">' . htmlspecialchars($emp->name ?? '-') . '</td>
                <td>' . htmlspecialchars($emp->department?->name ?? '-') . '</td>
                <td>' . number_format($row['base_salary'], 2) . '</td>
                <td style="color:#059669;font-weight:bold;">' . $row['increase_percent'] . '%</td>
                <td style="color:#059669;">' . number_format($row['increase_amount'], 2) . '</td>
                <td style="font-weight:bold;">' . number_format($row['new_salary'], 2) . '</td>
            </tr>';
            
            $totalCurrent += $row['base_salary'];
            $totalIncrease += $row['increase_amount'];
            $totalNew += $row['new_salary'];
        }
        
        $html .= '<tr style="background:#1e40af;color:white;font-weight:bold;">
            <td colspan="3">المجموع الكلي</td>
            <td>' . number_format($totalCurrent, 2) . '</td>
            <td>-</td>
            <td>' . number_format($totalIncrease, 2) . '</td>
            <td>' . number_format($totalNew, 2) . '</td>
        </tr></table>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateEmployeeDetailedReportHtml($org, $emp)
    {
        $insuranceTypes = [
            'none' => 'بدون تأمين',
            'health' => 'تأمين صحي',
            'social' => 'تأمين اجتماعي',
            'both' => 'تأمين صحي واجتماعي',
        ];
        
        $leaveStatusLabels = [
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليها',
            'rejected' => 'مرفوضة',
            'cancelled' => 'ملغاة',
        ];
        
        $leaveTypes = $emp->leaves->groupBy('type');
        $warningsByType = $emp->warningsRelation->groupBy('type');
        $advancesApproved = $emp->advances->where('status', 'approved');
        $advancesTotalPaid = $advancesApproved->sum(function($a) {
            return ($a->paid_installments ?? 0) * ($a->monthly_installment ?? 0);
        });
        
        $html = $this->getBaseStyles();
        
        // Header
        $html .= $this->getOfficialHeaderHtml($org);
        
        // Title
        $html .= '
        <div style="text-align:center;margin:15px 0;border:3px solid #1e40af;padding:15px;border-radius:10px;background:#eff6ff;">
            <h2 style="font-size:18px;margin:0;color:#1e40af;">تقرير موظف شامل ومفصل</h2>
            <p style="font-size:14px;margin:10px 0;font-weight:bold;color:#1e3a8a;">' . htmlspecialchars($emp->name) . '</p>
            <p style="font-size:10px;margin:0;color:#64748b;">رقم الموظف: ' . htmlspecialchars($emp->employee_number ?? $emp->file_number ?? '-') . ' | تاريخ التقرير: ' . now()->format('Y-m-d') . '</p>
        </div>';
        
        // Personal Info
        $html .= '
        <div class="section-header">اولا: البيانات الشخصية</div>
        <table class="info-table" style="width:100%;margin-bottom:10px;">
            <tr>
                <td style="width:25%;"><strong>الاسم:</strong> ' . htmlspecialchars($emp->name) . '</td>
                <td style="width:25%;"><strong>رقم الموظف:</strong> ' . htmlspecialchars($emp->employee_number ?? $emp->file_number ?? '-') . '</td>
                <td style="width:25%;"><strong>الرقم الوطني:</strong> ' . htmlspecialchars($emp->national_id ?? '-') . '</td>
                <td style="width:25%;"><strong>تاريخ الميلاد:</strong> ' . htmlspecialchars($emp->birth_date ?? '-') . '</td>
            </tr>
            <tr>
                <td><strong>الجنس:</strong> ' . htmlspecialchars($emp->gender ?? '-') . '</td>
                <td><strong>الحالة الاجتماعية:</strong> ' . htmlspecialchars($emp->marital_status ?? '-') . '</td>
                <td><strong>البريد:</strong> ' . htmlspecialchars($emp->email ?? '-') . '</td>
                <td><strong>الهاتف:</strong> ' . htmlspecialchars($emp->phone ?? '-') . '</td>
            </tr>
        </table>';
        
        // Appointment Info
        $html .= '
        <div class="section-header">ثانيا: بيانات التعيين</div>
        <table class="info-table" style="width:100%;margin-bottom:10px;">
            <tr>
                <td style="width:20%;"><strong>القسم:</strong> ' . htmlspecialchars($emp->department?->name ?? '-') . '</td>
                <td style="width:20%;"><strong>المسمى:</strong> ' . htmlspecialchars($emp->position ?? '-') . '</td>
                <td style="width:20%;"><strong>تاريخ التعيين:</strong> ' . htmlspecialchars($emp->hire_date ?? '-') . '</td>
                <td style="width:20%;"><strong>نوع العقد:</strong> ' . htmlspecialchars($emp->contract_type ?? '-') . '</td>
                <td style="width:20%;"><strong>الحالة:</strong> ' . htmlspecialchars($emp->status ?? '-') . '</td>
            </tr>
        </table>';
        
        // Salary Info
        $html .= '
        <div class="section-header">ثالثا: بيانات المرتب والبدلات</div>
        <table class="info-table" style="width:100%;margin-bottom:10px;">
            <tr>
                <td style="width:25%;"><strong>الراتب الاساسي:</strong> ' . number_format($emp->base_salary ?? 0, 2) . '</td>
                <td style="width:25%;"><strong>بدل الوظيفة:</strong> ' . number_format($emp->position_allowance ?? 0, 2) . '</td>
                <td style="width:25%;"><strong>اجمالي البدلات:</strong> ' . number_format($emp->compensations->sum('value'), 2) . '</td>
                <td style="width:25%;"><strong>اجمالي الحوافز:</strong> ' . number_format($emp->incentives ? $emp->incentives->sum('value') : 0, 2) . '</td>
            </tr>
            <tr>
                <td><strong>التامين:</strong> ' . ($insuranceTypes[$emp->insurance_type ?? 'none'] ?? '-') . '</td>
                <td><strong>قيمة التامين:</strong> ' . number_format($emp->insurance_amount ?? 0, 2) . '</td>
                <td><strong>البنك:</strong> ' . htmlspecialchars($emp->bank_name ?? '-') . '</td>
                <td><strong>رقم الحساب:</strong> ' . htmlspecialchars($emp->bank_account ?? '-') . '</td>
            </tr>
        </table>';
        
        // PAGE BREAK
        $html .= '<pagebreak>';
        
        // ===== PAGE 2: Leaves =====
        $html .= $this->getOfficialHeaderHtml($org);
        $html .= '
        <div class="section-header" style="font-size:13px;">رابعا: سجل الاجازات المفصل</div>
        
        <div class="summary-box">
            <strong>ملخص الاجازات:</strong>
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;margin-top:5px;">
                <span>اجمالي: <strong>' . $emp->leaves->count() . '</strong> اجازة (' . $emp->leaves->sum('days') . ' يوم)</span>
                <span>الموافق عليها: <strong>' . $emp->leaves->where('status', 'approved')->count() . '</strong></span>
                <span>المعلقة: <strong>' . $emp->leaves->where('status', 'pending')->count() . '</strong></span>
            </div>
        </div>';
        
        $html .= '
        <table style="margin-bottom:10px;">
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:12%;">النوع</th>
                <th style="width:10%;">من تاريخ</th>
                <th style="width:10%;">الي تاريخ</th>
                <th style="width:7%;">الايام</th>
                <th style="width:10%;">الحالة</th>
                <th style="width:8%;">مدفوعة</th>
                <th style="width:38%;">ملاحظات</th>
            </tr>';
        $i = 1;
        foreach ($emp->leaves as $leave) {
            $statusClass = $leave->status === 'approved' ? 'success' : ($leave->status === 'rejected' ? 'danger' : 'warning');
            $statusLabel = $leaveStatusLabels[$leave->status] ?? $leave->status;
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $html .= '<tr style="background:' . $bg . '" class="' . $statusClass . '">
                <td style="text-align:center;">' . $i . '</td>
                <td>' . htmlspecialchars($leave->type ?? '-') . '</td>
                <td style="text-align:center;">' . $leave->from_date . '</td>
                <td style="text-align:center;">' . $leave->to_date . '</td>
                <td style="text-align:center;font-weight:bold;">' . ($leave->days ?? 0) . '</td>
                <td style="text-align:center;">' . htmlspecialchars($statusLabel) . '</td>
                <td style="text-align:center;">' . ($leave->paid ? 'نعم' : 'لا') . '</td>
                <td>' . htmlspecialchars($leave->reason ?? '-') . '</td>
            </tr>';
            $i++;
        }
        if ($emp->leaves->count() === 0) {
            $html .= '<tr><td colspan="8" style="text-align:center;">لا توجد اجازات مسجلة</td></tr>';
        }
        $html .= '</table>';
        
        // PAGE BREAK
        $html .= '<pagebreak>';
        
        // ===== PAGE 3: Warnings =====
        $html .= $this->getOfficialHeaderHtml($org);
        $html .= '
        <div class="section-header" style="font-size:13px;">خامسا: سجل الانذارات المفصل</div>
        
        <div class="summary-box">
            <strong>ملخص الانذارات:</strong> ' . $emp->warningsRelation->count() . ' انذار
        </div>
        
        <table style="margin-bottom:10px;">
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:15%;">النوع</th>
                <th style="width:12%;">التاريخ</th>
                <th style="width:12%;">الحالة</th>
                <th style="width:56%;">السبب</th>
            </tr>';
        $i = 1;
        foreach ($emp->warningsRelation as $warning) {
            $bg = $i % 2 === 0 ? '#fee2e2' : '#fef2f2';
            $html .= '<tr style="background:' . $bg . '">
                <td style="text-align:center;">' . $i . '</td>
                <td>' . htmlspecialchars($warning->type ?? '-') . '</td>
                <td style="text-align:center;">' . $warning->date . '</td>
                <td style="text-align:center;">' . htmlspecialchars($warning->status ?? '-') . '</td>
                <td>' . htmlspecialchars($warning->reason ?? '-') . '</td>
            </tr>';
            $i++;
        }
        if ($emp->warningsRelation->count() === 0) {
            $html .= '<tr><td colspan="5" style="text-align:center;">لا توجد انذارات مسجلة</td></tr>';
        }
        $html .= '</table>';
        
        // PAGE BREAK
        $html .= '<pagebreak>';
        
        // ===== PAGE 4: Advances =====
        $html .= $this->getOfficialHeaderHtml($org);
        $html .= '
        <div class="section-header" style="font-size:13px;">سادسا: سجل السلف المفصل</div>
        
        <div class="summary-box">
            <strong>ملخص السلف:</strong>
            <span>اجمالي: <strong>' . $emp->advances->count() . '</strong></span> |
            <span>اجمالي القيمة: <strong>' . number_format($emp->advances->sum('amount'), 2) . '</strong></span> |
            <span>المتبقي: <strong>' . number_format($emp->advances->sum('remaining_amount'), 2) . '</strong></span>
        </div>
        
        <table style="margin-bottom:10px;">
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:10%;">القيمة</th>
                <th style="width:8%;">التاريخ</th>
                <th style="width:10%;">الحالة</th>
                <th style="width:8%;">الاقساط</th>
                <th style="width:10%;">قسط شهري</th>
                <th style="width:10%;">المدفوع</th>
                <th style="width:10%;">المتبقي</th>
                <th style="width:29%;">ملاحظات</th>
            </tr>';
        $i = 1;
        foreach ($emp->advances as $advance) {
            $statusLabel = $advance->status === 'approved' ? 'موافق' : ($advance->status === 'rejected' ? 'مرفوض' : 'قيد الانتظار');
            $paidTotal = ($advance->paid_installments ?? 0) * ($advance->monthly_installment ?? 0);
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $html .= '<tr style="background:' . $bg . '">
                <td style="text-align:center;">' . $i . '</td>
                <td style="text-align:center;font-weight:bold;">' . number_format($advance->amount, 2) . '</td>
                <td style="text-align:center;">' . $advance->date . '</td>
                <td style="text-align:center;">' . $statusLabel . '</td>
                <td style="text-align:center;">' . ($advance->installment_count ?? 0) . '</td>
                <td style="text-align:center;">' . number_format($advance->monthly_installment ?? 0, 2) . '</td>
                <td style="text-align:center;">' . number_format($paidTotal, 2) . '</td>
                <td style="text-align:center;font-weight:bold;color:#dc2626;">' . number_format($advance->remaining_amount ?? 0, 2) . '</td>
                <td>' . htmlspecialchars($advance->reason ?? '-') . '</td>
            </tr>';
            $i++;
        }
        if ($emp->advances->count() === 0) {
            $html .= '<tr><td colspan="9" style="text-align:center;">لا توجد سلف مسجلة</td></tr>';
        }
        $html .= '</table>';
        
        // PAGE BREAK
        $html .= '<pagebreak>';
        
        // ===== Final Page: Signature Section =====
        $html .= $this->getOfficialHeaderHtml($org);
        $html .= '
        <div class="section-header">التوقيعات والاعتمادات</div>
        
        <div style="margin:30px 0;padding:20px;background:#f0f9ff;border:2px solid #0ea5e9;border-radius:10px;">
            <h3 style="text-align:center;color:#0369a1;margin:0 0 15px 0;">اعتماد التقرير</h3>
            <p style="text-align:center;color:#64748b;margin:0 0 20px 0;">تم إصدار هذا التقرير من نظام Jawda HR لإدارة الموارد البشرية</p>
        </div>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateDetailedSalaryReportHtml($org, $salaryData, $monthName, $year, $currencySymbol)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . $headerHtml . '
        
        <div class="title-box">
            <h2>كشف رواتب الموظفين</h2>
            <p>عن شهر ' . $monthName . ' ' . $year . ' | تاريخ الطباعة: ' . now()->format('Y-m-d') . '</p>
        </div>';
        
        // Earnings table
        $html .= '
        <p style="font-weight:bold;color:#1e3a5f;font-size:12px;margin:15px 0 5px 0;padding:8px 12px;background:#eef2ff;border-radius:6px;border-right:3px solid #1e3a5f;">جدول الاستحقاقات</p>
        <table>
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:20%">الاسم</th>
                    <th style="width:14%">القسم</th>
                    <th style="width:10%">الراتب الأساسي</th>
                    <th style="width:8%">بدل وظيفي</th>
                    <th style="width:10%">البدلات</th>
                    <th style="width:8%">الحوافز</th>
                    <th style="width:10%">إجمالي الاستحقاقات</th>
                </tr>
            </thead>
            <tbody>';
        
        $totals = ['base' => 0, 'position' => 0, 'allowances' => 0, 'incentives' => 0, 'gross' => 0];
        
        foreach ($salaryData as $i => $emp) {
            $totalAllowances = array_sum(array_column($emp['allowances'], 'amount'));
            $totalIncentives = array_sum(array_column($emp['incentives'], 'amount'));
            
            $totals['base'] += $emp['base_salary'];
            $totals['position'] += $emp['position_allowance'];
            $totals['allowances'] += $totalAllowances;
            $totals['incentives'] += $totalIncentives;
            $totals['gross'] += $emp['gross_salary'];
            
            $rowClass = $i % 2 === 0 ? 'row-even' : 'row-odd';
            $html .= '<tr class="' . $rowClass . '">
                <td>' . ($i + 1) . '</td>
                <td style="font-weight:bold;text-align:right;">' . htmlspecialchars($emp['employee']->name) . '</td>
                <td>' . htmlspecialchars($emp['employee']->department?->name ?? '-') . '</td>
                <td>' . number_format($emp['base_salary'], 2) . '</td>
                <td>' . number_format($emp['position_allowance'], 2) . '</td>
                <td>' . number_format($totalAllowances, 2) . '</td>
                <td>' . number_format($totalIncentives, 2) . '</td>
                <td class="font-bold success-bg">' . number_format($emp['gross_salary'], 2) . '</td>
            </tr>';
        }
        
        $html .= '<tr class="total-row">
            <td colspan="3">المجموع</td>
            <td>' . number_format($totals['base'], 2) . '</td>
            <td>' . number_format($totals['position'], 2) . '</td>
            <td>' . number_format($totals['allowances'], 2) . '</td>
            <td>' . number_format($totals['incentives'], 2) . '</td>
            <td>' . number_format($totals['gross'], 2) . '</td>
        </tr></tbody></table>';
        
        // Deductions table
        $html .= '
        <p style="font-weight:bold;color:#dc2626;font-size:12px;margin:15px 0 5px 0;padding:8px 12px;background:#fef2f2;border-radius:6px;border-right:3px solid #dc2626;">جدول الخصومات</p>
        
        <table>
            <thead>
                <tr style="background:#dc2626;">
                    <th style="width:4%">#</th>
                    <th style="width:18%">الاسم</th>
                    <th style="width:8%">التأمين</th>
                    <th style="width:8%">الخصومات</th>
                    <th style="width:9%">خصم الحضور</th>
                    <th style="width:9%">خصم السلف</th>
                    <th style="width:10%">إجمالي الخصومات</th>
                    <th style="width:10%">صافي الراتب</th>
                </tr>
            </thead>
            <tbody>';
        
        $totals2 = ['insurance' => 0, 'deductions' => 0, 'attendance' => 0, 'advances' => 0, 'total_ded' => 0, 'net' => 0];
        
        foreach ($salaryData as $i => $emp) {
            $attendanceDeduction = $emp['attendance_deductions'] ?? 0;
            $advanceDeduction = $emp['advance_deductions'] ?? 0;
            $totalDed = $emp['insurance_amount'] + $emp['deductions'] + $attendanceDeduction + $advanceDeduction;
            
            $totals2['insurance'] += $emp['insurance_amount'];
            $totals2['deductions'] += $emp['deductions'];
            $totals2['attendance'] += $attendanceDeduction;
            $totals2['advances'] += $advanceDeduction;
            $totals2['total_ded'] += $totalDed;
            $totals2['net'] += $emp['net_salary'];
            
            $rowClass = $i % 2 === 0 ? 'row-even' : 'row-odd';
            $html .= '<tr class="' . $rowClass . '">
                <td>' . ($i + 1) . '</td>
                <td style="font-weight:bold;text-align:right;">' . htmlspecialchars($emp['employee']->name) . '</td>
                <td>' . number_format($emp['insurance_amount'], 2) . '</td>
                <td>' . number_format($emp['deductions'], 2) . '</td>
                <td>' . number_format($attendanceDeduction, 2) . '</td>
                <td>' . number_format($advanceDeduction, 2) . '</td>
                <td class="font-bold danger-bg">' . number_format($totalDed, 2) . '</td>
                <td class="font-bold success-bg">' . number_format($emp['net_salary'], 2) . '</td>
            </tr>';
        }
        
        $html .= '<tr style="background:#dc2626;color:white;font-weight:bold;">
            <td colspan="2">المجموع</td>
            <td>' . number_format($totals2['insurance'], 2) . '</td>
            <td>' . number_format($totals2['deductions'], 2) . '</td>
            <td>' . number_format($totals2['attendance'], 2) . '</td>
            <td>' . number_format($totals2['advances'], 2) . '</td>
            <td>' . number_format($totals2['total_ded'], 2) . '</td>
            <td>' . number_format($totals2['net'], 2) . '</td>
        </tr></tbody></table>';
        
        // Summary
        $html .= '
        <div class="summary-box" style="margin-top:15px;background:#f0fdf4;border-color:#16a34a;">
            <table style="border:none;width:100%;">
                <tr style="border:none;">
                    <td style="border:none;width:25%;font-weight:bold;">عدد الموظفين:</td>
                    <td style="border:none;width:25%;">' . count($salaryData) . ' موظف</td>
                    <td style="border:none;width:25%;font-weight:bold;">إجمالي المستحق:</td>
                    <td style="border:none;width:25%;font-weight:bold;color:#16a34a;">' . number_format($totals['gross'], 2) . ' ' . $currencySymbol . '</td>
                </tr>
                <tr style="border:none;">
                    <td style="border:none;font-weight:bold;">صافي المرتبات:</td>
                    <td style="border:none;font-weight:bold;color:#16a34a;font-size:14px;">' . number_format($totals2['net'], 2) . ' ' . $currencySymbol . '</td>
                    <td style="border:none;font-weight:bold;">إجمالي الخصومات:</td>
                    <td style="border:none;color:#dc2626;">' . number_format($totals2['total_ded'], 2) . ' ' . $currencySymbol . '</td>
                </tr>
            </table>
        </div>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateIncomeTaxReportHtml($org, $taxData, $year)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . $headerHtml . '
        
        <div class="title-box" style="border-color:#ea580c;background:linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);">
            <h2 style="color:#ea580c;">تقرير ضريبة الدخل</h2>
            <p style="color:#c2410c;">السنة: ' . $year . ' | تاريخ الطباعة: ' . now()->format('Y-m-d') . '</p>
        </div>
        
        <table>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:25%">الاسم</th>
                <th style="width:18%">القسم</th>
                <th style="width:13%">المرتب الشهري</th>
                <th style="width:13%">المرتب السنوي</th>
                <th style="width:13%">الضريبة الشهرية</th>
                <th style="width:13%">الضريبة السنوية</th>
            </tr>';
        
        $totalMonthly = 0;
        $totalAnnual = 0;
        $totalMonthlyTax = 0;
        $totalAnnualTax = 0;
        
        foreach ($taxData as $i => $row) {
            $emp = $row['employee'];
            $rowClass = $i % 2 === 0 ? 'row-even' : 'row-odd';
            $html .= '<tr class="' . $rowClass . '">
                <td>' . ($i + 1) . '</td>
                <td style="text-align:right;font-weight:bold;">' . htmlspecialchars($emp->name ?? '-') . '</td>
                <td>' . htmlspecialchars($emp->department?->name ?? '-') . '</td>
                <td>' . number_format($row['monthly_salary'], 2) . '</td>
                <td>' . number_format($row['annual_salary'], 2) . '</td>
                <td style="color:#ea580c;font-weight:bold;">' . number_format($row['monthly_tax'], 2) . '</td>
                <td style="color:#ea580c;font-weight:bold;">' . number_format($row['annual_tax'], 2) . '</td>
            </tr>';
            
            $totalMonthly += $row['monthly_salary'];
            $totalAnnual += $row['annual_salary'];
            $totalMonthlyTax += $row['monthly_tax'];
            $totalAnnualTax += $row['annual_tax'];
        }
        
        $html .= '<tr style="background:#ea580c;color:white;font-weight:bold;">
            <td colspan="3">الإجمالي</td>
            <td>' . number_format($totalMonthly, 2) . '</td>
            <td>' . number_format($totalAnnual, 2) . '</td>
            <td>' . number_format($totalMonthlyTax, 2) . '</td>
            <td>' . number_format($totalAnnualTax, 2) . '</td>
        </tr></table>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateLeaveWarningReportHtml($org, $data, $year)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . $headerHtml . '
        
        <div class="title-box" style="border-color:#7c3aed;background:linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);">
            <h2 style="color:#7c3aed;">تقرير الإجازات والإنذارات</h2>
            <p style="color:#6d28d9;">السنة: ' . $year . ' | تاريخ الطباعة: ' . now()->format('Y-m-d') . '</p>
        </div>
        
        <table>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:30%">الاسم</th>
                <th style="width:20%">القسم</th>
                <th style="width:15%">إجمالي الإجازات</th>
                <th style="width:15%">إجمالي الإنذارات</th>
                <th style="width:15%">ملخص الحالة</th>
            </tr>';
        
        foreach ($data as $i => $row) {
            $emp = $row['employee'];
            $leavesCount = $row['leaves']->count();
            $warningsCount = $row['warnings']->count();
            $summary = '';
            if ($leavesCount > 0 && $warningsCount > 0) $summary = 'إجازات وإنذارات';
            elseif ($leavesCount > 0) $summary = 'إجازات فقط';
            elseif ($warningsCount > 0) $summary = 'إنذارات فقط';
            else $summary = 'لا توجد سجلات';
            
            $rowClass = $i % 2 === 0 ? 'row-even' : 'row-odd';
            $html .= '<tr class="' . $rowClass . '">
                <td>' . ($i + 1) . '</td>
                <td style="text-align:right;font-weight:bold;">' . htmlspecialchars($emp->name ?? '-') . '</td>
                <td>' . htmlspecialchars($emp->department?->name ?? '-') . '</td>
                <td style="text-align:center;">' . $leavesCount . '</td>
                <td style="text-align:center;color:#dc2626;">' . $warningsCount . '</td>
                <td>' . $summary . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateDepartmentReportHtml($org, $data, $year)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . $headerHtml . '
        
        <div class="title-box" style="border-color:#0891b2;background:linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);">
            <h2 style="color:#0891b2;">تقرير الأقسام</h2>
            <p style="color:#0e7490;">السنة: ' . $year . ' | تاريخ الطباعة: ' . now()->format('Y-m-d') . '</p>
        </div>
        
        <table>
            <tr>
                <th style="width:10%">#</th>
                <th style="width:35%">القسم</th>
                <th style="width:20%">عدد الموظفين</th>
                <th style="width:35%">إجمالي المرتبات</th>
            </tr>';
        
        $totalEmp = 0;
        $totalSal = 0;
        
        foreach ($data as $i => $row) {
            $rowClass = $i % 2 === 0 ? 'row-even' : 'row-odd';
            $html .= '<tr class="' . $rowClass . '">
                <td>' . ($i + 1) . '</td>
                <td style="text-align:right;font-weight:bold;">' . htmlspecialchars($row['name'] ?? '-') . '</td>
                <td style="text-align:center;">' . ($row['employee_count'] ?? 0) . '</td>
                <td style="text-align:center;">' . number_format($row['total_salaries'] ?? 0, 2) . '</td>
            </tr>';
            $totalEmp += $row['employee_count'] ?? 0;
            $totalSal += $row['total_salaries'] ?? 0;
        }
        
        $html .= '<tr style="background:#0891b2;color:white;font-weight:bold;">
            <td colspan="2">الإجمالي</td>
            <td style="text-align:center;">' . $totalEmp . '</td>
            <td style="text-align:center;">' . number_format($totalSal, 2) . '</td>
        </tr></table>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function generateLetterContent($type, $employee, $org, $params)
    {
        $titles = [
            'termination' => 'خطاب إنهاء خدمة',
            'warning' => 'إنذار',
            'good_conduct' => 'شهادة حسن سير وسلوك',
            'salary_verification' => 'شهادة إثبات راتب',
            'experience' => 'شهادة خبرة',
        ];
        
        $title = $titles[$type] ?? 'خطاب رسمي';
        
        $body = '';
        
        switch ($type) {
            case 'termination':
                $body = '
                    <p style="text-align:justify;line-height:2;">نرجو التكرم بالعلم بأنه قد تم إنهاء خدمة الموظف/ة <strong>' . htmlspecialchars($employee->name) . '</strong> اعتباراً من تاريخ <strong>' . ($params['termination_date'] ?? now()->format('Y-m-d')) . '</strong> وذلك لـ (' . htmlspecialchars($params['reason'] ?? 'أسباب خاصة') . ').</p>
                    <p style="text-align:justify;line-height:2;">لقد عمل معنا في قسم <strong>' . htmlspecialchars($employee->department?->name ?? '-') . '</strong> بمهنة <strong>' . htmlspecialchars($employee->position ?? '-') . '</strong>، ونشكره على الفترة التي أمضاها معنا، ونتمنى له/لها التوفيق في مسيرته/مسيرتها المهنية.</p>
                ';
                break;
                
            case 'warning':
                $body = '
                    <p style="text-align:justify;line-height:2;">نود إبلاغ الموظف/ة <strong>' . htmlspecialchars($employee->name) . '</strong> بأننا قد رصدنا مخالفة/مخالفات (' . htmlspecialchars($params['warning_reason'] ?? '-') . ') وذلك في تاريخ ' . now()->format('Y-m-d') . '.</p>
                    <p style="text-align:justify;line-height:2;">نأمل من سيادتكم الالتزام بالأنظمة والسياسات المتبعة في المؤسسة، وتجنب تكرار مثل هذه المخالفات مستقبلاً، وإلا سنضطر لاتخاذ الإجراءات اللازمة وفقاً للنظام.</p>
                ';
                break;
                
            case 'good_conduct':
                $body = '
                    <p style="text-align:justify;line-height:2;">تشهد مؤسسة <strong>' . htmlspecialchars($org['name'] ?? 'Jawda HR') . '</strong> بأن الموظف/ة <strong>' . htmlspecialchars($employee->name) . '</strong> كان/تعمل لديها خلال الفترة من <strong>' . htmlspecialchars($employee->hire_date ?? '-') . '</strong> وحتى تاريخه.</p>
                    <p style="text-align:justify;line-height:2;">وخلال فترة عمله/عملها، فقد شهدنا عليه/عليها بحسن السير والسلوك، والأداء المهني المتميز، والتزامه/التزامها بكافة السياسات والأنظمة المتبعة في المؤسسة.</p>
                ';
                break;
                
            case 'salary_verification':
                $body = '
                    <p style="text-align:justify;line-height:2;">تشهد مؤسسة <strong>' . htmlspecialchars($org['name'] ?? 'Jawda HR') . '</strong> بأن الموظف/ة <strong>' . htmlspecialchars($employee->name) . '</strong> يتقاض/تتقاضى راتباً شهرياً قدره <strong>' . number_format($employee->base_salary ?? 0, 2) . ' ' . ($org['currency_symbol'] ?? 'جنيه سوداني') . '</strong>.</p>
                    <p style="text-align:justify;line-height:2;">وذلك وفقاً للراتب المسجل في سجلات المؤسسة.</p>
                ';
                break;
                
            case 'experience':
                $body = '
                    <p style="text-align:justify;line-height:2;">تشهد مؤسسة <strong>' . htmlspecialchars($org['name'] ?? 'Jawda HR') . '</strong> بأن الموظف/ة <strong>' . htmlspecialchars($employee->name) . '</strong> قد عمل/ت لديها في قسم <strong>' . htmlspecialchars($employee->department?->name ?? '-') . '</strong> بمهنة <strong>' . htmlspecialchars($employee->position ?? '-') . '</strong> خلال الفترة من <strong>' . htmlspecialchars($employee->hire_date ?? '-') . '</strong>.</p>
                    <p style="text-align:justify;line-height:2;">وخلال فترة عمله/عملها، اكتسب/ت خبرات ومهارات متميزة في مجال عملها، وحاز/حازت على ثقة الإدارة والزملاء.</p>
                ';
                break;
                
            default:
                $body = '<p style="text-align:justify;line-height:2;">نرجو التكرم بالعلم بما جاء في هذا الخطاب.</p>';
        }
        
        $body .= '<p style="text-align:justify;line-height:2;">وتفضلوا بقبول فائق الاحترام والتقدير.</p>';
        
        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    private function generateLetterHtml($org, $employee, $content)
    {
        $headerHtml = $this->getOfficialHeaderHtml($org);
        $styles = $this->getBaseStyles();
        
        $html = $styles . '
        <style>
            body { font-family: Amiri, dejavusans, sans-serif; line-height: 2; }
            .letter-box { background: #fff; padding: 20px 30px; margin: 15px 0; }
            .letter-title { font-size: 20px; font-weight: bold; margin: 25px 0; text-align: center; color: #1e3a5f; }
            .content-body { text-align: justify; margin: 30px 0; font-size: 12px; line-height: 2; }
            .ref-box { background: #eef2ff; border: 1px solid #c7d2fe; padding: 8px 15px; border-radius: 6px; margin: 15px 0; text-align: center; }
            .employee-info { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; margin: 15px 0; }
        </style>
        
        ' . $headerHtml . '
        
        <div class="ref-box">
            <p style="margin:0;font-size:10px;color:#1e3a5f;">
                <strong>المرجع:</strong> ' . $org['name'] . '-DOC-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT) . '-' . date('Y') . '
            </p>
        </div>
        
        <div class="letter-box">
            <div class="letter-title">' . $content['title'] . '</div>
            
            <div class="employee-info">
                <table style="width:100%;border:none;font-size:11px;">
                    <tr>
                        <td style="border:none;padding:3px;width:25%;"><strong>التاريخ:</strong> ' . now()->format('Y-m-d') . '</td>
                        <td style="border:none;padding:3px;width:25%;"><strong>الاسم:</strong> ' . htmlspecialchars($employee->name) . '</td>
                        <td style="border:none;padding:3px;width:25%;"><strong>القسم:</strong> ' . htmlspecialchars($employee->department?->name ?? '-') . '</td>
                        <td style="border:none;padding:3px;width:25%;"><strong>المهنة:</strong> ' . htmlspecialchars($employee->position ?? '-') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="content-body">' . $content['body'] . '</div>
        </div>';
        
        $html .= $this->getOfficialFooterHtml($org);
        
        return $html;
    }

    private function calculateIncomeTax($monthlySalary)
    {
        $brackets = $this->getDefaultTaxBrackets();
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
