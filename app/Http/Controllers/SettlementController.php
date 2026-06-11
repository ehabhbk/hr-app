<?php

namespace App\Http\Controllers;

use App\Models\SettlementSetting;
use App\Models\Employee;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index()
    {
        $settings = SettlementSetting::all();
        
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting->key] = $setting->value;
        }
        
        return response()->json(['data' => $formatted]);
    }

    public function show($key)
    {
        $setting = SettlementSetting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json(['error' => 'Setting not found'], 404);
        }
        
        return response()->json(['data' => $setting->value]);
    }

    public function update(Request $request, $key)
    {
        $setting = SettlementSetting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json(['error' => 'Setting not found'], 404);
        }
        
        $data = $request->all();
        $setting->update(['value' => $data]);
        
        return response()->json([
            'data' => $setting->value,
            'message' => 'تم الحفظ بنجاح'
        ]);
    }

    public function updateAll(Request $request)
    {
        $data = $request->all();
        
        foreach ($data as $key => $value) {
            SettlementSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        
        return response()->json([
            'message' => 'تم الحفظ بنجاح',
            'data' => SettlementSetting::all()->pluck('value', 'key')
        ]);
    }

    public function calculateEmployee($employeeId)
    {
        $employee = Employee::with([
            'department',
            'leaves' => function($q) {
                $q->where('status', 'approved');
            },
            'advances' => function($q) {
                $q->where('status', 'approved');
            }
        ])->findOrFail($employeeId);
        
        $settlement = SettlementSetting::calculateFullSettlement($employee);
        
        return response()->json(['data' => $settlement]);
    }

    public function calculateAllEmployees(Request $request)
    {
        $departmentId = $request->input('department_id');
        
        $query = Employee::with([
            'department',
            'leaves' => function($q) {
                $q->where('status', 'approved');
            },
            'advances' => function($q) {
                $q->where('status', 'approved');
            }
        ])->where('status', 'active');
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        $employees = $query->get();
        
        $settlements = [];
        foreach ($employees as $employee) {
            $settlements[] = SettlementSetting::calculateFullSettlement($employee);
        }
        
        $totals = [
            'total_employees' => count($settlements),
            'total_severance' => array_sum(array_column($settlements, 'severance_pay')),
            'total_notice' => array_sum(array_column($settlements, 'notice_period_amount')),
            'total_leave' => array_sum(array_column($settlements, 'unused_leave_amount')),
            'total_due' => array_sum(array_column($settlements, 'total_due')),
            'total_deduct' => array_sum(array_column($settlements, 'total_deduct')),
            'total_net' => array_sum(array_column($settlements, 'net_settlement')),
        ];
        
        return response()->json([
            'data' => $settlements,
            'totals' => $totals
        ]);
    }

    public function exportSettlementPdf($employeeId)
    {
        $employee = Employee::with([
            'department',
            'leaves' => function($q) {
                $q->where('status', 'approved');
            },
            'advances' => function($q) {
                $q->where('status', 'approved');
            }
        ])->findOrFail($employeeId);
        
        $org = SettlementSetting::getOrganizationData();
        $settlement = SettlementSetting::calculateFullSettlement($employee);
        
        ob_start();
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        ob_end_clean();
        
        $pdf->SetCreator('Jawda HR');
        $pdf->SetAuthor($org['name'] ?? 'Jawda HR');
        $pdf->SetTitle('تسوية مستحقات الموظف');
        $pdf->SetSubject('تسوية إنهاء الخدمة');
        
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->SetAutoPageBreak(true, 25);
        
        $pdf->AddPage();
        
        $html = $this->generateSettlementPdfHtml($org, $settlement, $employee);
        $pdf->writeHTML($html, true, false, true, false, 'R');
        
        $pdfContent = $pdf->Output('settlement_' . $employee->name . '.pdf', 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="تسوية_' . $employee->name . '.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }

    private function generateSettlementPdfHtml($org, $settlement, $employee)
    {
        $logoHtml = '';
        if (!empty($org['logo'])) {
            $logoPath = public_path('storage/' . $org['logo']);
            if (file_exists($logoPath)) {
                $logoBase64 = base64_encode(file_get_contents($logoPath));
                $logoExt = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoMime = $logoExt === 'jpg' || $logoExt === 'jpeg' ? 'image/jpeg' : 'image/png';
                $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoBase64 . '" style="height:60px;width:60px;object-fit:contain;">';
            }
        }
        
        $stampHtml = '';
        if (!empty($org['stamp'])) {
            $stampPath = public_path('storage/' . $org['stamp']);
            if (file_exists($stampPath)) {
                $stampBase64 = base64_encode(file_get_contents($stampPath));
                $stampExt = pathinfo($stampPath, PATHINFO_EXTENSION);
                $stampMime = $stampExt === 'jpg' || $stampExt === 'jpeg' ? 'image/jpeg' : 'image/png';
                $stampHtml = '<img src="data:' . $stampMime . ';base64,' . $stampBase64 . '" style="height:60px;width:60px;object-fit:contain;opacity:0.85;">';
            }
        }
        
        $logoPlaceholder = '<div style="width:60px;height:60px;background:#f3f4f6;border:2px dashed #9ca3af;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:10px;">شعار</div>';
        $stampPlaceholder = '<div style="width:60px;height:60px;border:2px dashed #9ca3af;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:8px;">ختم</div>';
        
        $html = '
        <style>
            body { font-family: aealarabiya, sans-serif; direction: rtl; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #334155; padding: 6px; }
            th { background: #1e40af; color: white; font-weight: bold; text-align: center; }
            .header-table td { border: none; }
            .section-header { background: #059669; color: white; padding: 8px; font-weight: bold; text-align: center; margin-top: 15px; }
            .total-row { background: #dcfce7; font-weight: bold; }
            .deduct-row { background: #fee2e2; }
            .net-row { background: #1e40af; color: white; font-weight: bold; }
        </style>
        
        <table class="header-table">
            <tr>
                <td style="width:70px;text-align:center;border:none;">' . ($logoHtml ?: $logoPlaceholder) . '</td>
                <td style="text-align:center;border:none;padding:10px;">
                    <h1 style="font-size:18px;margin:0;color:#1e40af;">' . htmlspecialchars($org['name'] ?? 'Jawda HR') . '</h1>
                    <p style="font-size:10px;color:#666;margin:3px 0;">' . htmlspecialchars($org['address'] ?? '') . ' | ' . htmlspecialchars($org['phone'] ?? '') . '</p>
                </td>
                <td style="width:70px;border:none;"></td>
            </tr>
        </table>
        <div style="height:3px;background:linear-gradient(to left,#1e40af,#3b82f6,#1e40af);margin:10px 0;"></div>
        
        <div style="text-align:center;margin:15px 0;border:3px solid #1e40af;padding:15px;border-radius:10px;background:#eff6ff;">
            <h2 style="font-size:16px;margin:0;color:#1e40af;">تسوية مستحقات الموظف</h2>
            <p style="font-size:12px;margin:8px 0;font-weight:bold;">' . htmlspecialchars($employee->name) . '</p>
            <p style="font-size:10px;margin:0;color:#64748b;">رقم المرجع: ' . $org['name'] . '-SETTLE-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT) . '-' . date('Y') . '</p>
        </div>
        
        <div class="section-header">اولا: معلومات الخدمة</div>
        <table>
            <tr>
                <td style="width:25%;font-weight:bold;">الاسم:</td>
                <td style="width:25%;">' . htmlspecialchars($employee->name) . '</td>
                <td style="width:25%;font-weight:bold;">القسم:</td>
                <td style="width:25%;">' . htmlspecialchars($employee->department?->name ?? '-') . '</td>
            </tr>
            <tr>
                <td style="font-weight:bold;">تاريخ التعيين:</td>
                <td>' . $settlement['hire_date'] . '</td>
                <td style="font-weight:bold;">تاريخ انتهاء الخدمة:</td>
                <td>' . $settlement['service_end_date'] . '</td>
            </tr>
            <tr>
                <td style="font-weight:bold;">سنوات الخدمة:</td>
                <td>' . $settlement['years_of_service'] . ' سنة و ' . $settlement['months_of_service'] . ' شهر</td>
                <td style="font-weight:bold;">إجمالي الأشهر:</td>
                <td>' . $settlement['total_months'] . ' شهر</td>
            </tr>
        </table>
        
        <div class="section-header">ثانيا: بيانات المرتب</div>
        <table>
            <tr>
                <td style="width:25%;font-weight:bold;">الراتب الأساسي:</td>
                <td style="width:25%;">' . number_format($settlement['base_salary'], 2) . '</td>
                <td style="width:25%;font-weight:bold;">بدل الوظيفة:</td>
                <td style="width:25%;">' . number_format($settlement['position_allowance'], 2) . '</td>
            </tr>
            <tr>
                <td style="font-weight:bold;">الراتب الإجمالي:</td>
                <td style="font-weight:bold;background:#dcfce7;">' . number_format($settlement['gross_salary'], 2) . '</td>
                <td style="font-weight:bold;">الراتب اليومي:</td>
                <td>' . number_format($settlement['daily_rate'], 2) . '</td>
            </tr>
        </table>
        
        <div class="section-header">ثالثا: المستحقات</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">#</th>
                    <th style="width:50%;">البند</th>
                    <th style="width:20%;">التفاصيل</th>
                    <th style="width:25%;">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>مكافأة إنهاء الخدمة</td>
                    <td>' . $settlement['years_of_service'] . ' سنة × ' . number_format($settlement['gross_salary'] / 12, 2) . '</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['severance_pay'], 2) . '</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>تعويض فترة الإخطار</td>
                    <td>' . $settlement['notice_period_days'] . ' يوم × ' . number_format($settlement['daily_rate'], 2) . '</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['notice_period_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>استبدال الإجازات غير المستخدمة</td>
                    <td>' . $settlement['unused_leave_days'] . ' يوم × ' . number_format($settlement['daily_rate'], 2) . '</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['unused_leave_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>بدل النقل المستحق</td>
                    <td>-</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['transport_allowance'], 2) . '</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>بدل السكن المستحق</td>
                    <td>-</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['housing_allowance'], 2) . '</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td>بدل الطعام المستحق</td>
                    <td>-</td>
                    <td style="text-align:center;font-weight:bold;">' . number_format($settlement['food_allowance'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3">إجمالي المستحقات</td>
                    <td style="text-align:center;">' . number_format($settlement['total_due'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-header" style="background:#dc2626;">رابعا: الخصومات</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">#</th>
                    <th style="width:50%;">البند</th>
                    <th style="width:20%;">التفاصيل</th>
                    <th style="width:25%;">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <tr class="deduct-row">
                    <td>1</td>
                    <td>سلفيات مستحقة الخصم</td>
                    <td>أقساط متبقية</td>
                    <td style="text-align:center;font-weight:bold;color:#dc2626;">' . number_format($settlement['remaining_advances'], 2) . '</td>
                </tr>
                <tr class="deduct-row">
                    <td colspan="3">إجمالي الخصومات</td>
                    <td style="text-align:center;">' . number_format($settlement['total_deduct'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-header" style="background:#1e40af;">خامسا: صافي التسوية</div>
        <table>
            <tr class="net-row">
                <td style="width:75%;text-align:center;font-size:14px;">صافي المستحقات بعد الخصومات</td>
                <td style="text-align:center;font-size:14px;">' . number_format($settlement['net_settlement'], 2) . ' ' . ($org['currency_symbol'] ?? 'جنيه') . '</td>
            </tr>
        </table>
        
        <div style="margin-top:30px;">
            <table style="width:100%;border:none;">
                <tr>
                    <td style="width:33%;text-align:center;border:none;vertical-align:top;">
                        <div style="min-height:60px;display:flex;align-items:center;justify-content:center;">' . ($stampHtml ?: $stampPlaceholder) . '</div>
                        <p style="font-size:9px;color:#374151;margin:5px 0 0 0;">ختم المؤسسة</p>
                    </td>
                    <td style="width:33%;text-align:center;border:none;vertical-align:top;">
                        <div style="border-bottom:2px solid #1e40af;width:100px;height:30px;margin:0 auto;"></div>
                        <p style="font-size:9px;color:#374151;margin:5px 0 0 0;">مدير الموارد البشرية</p>
                        <p style="font-size:8px;color:#6b7280;margin:2px 0;">الاسم: ........................</p>
                        <p style="font-size:8px;color:#6b7280;margin:2px 0;">التوقيع: ....................</p>
                    </td>
                    <td style="width:33%;text-align:center;border:none;vertical-align:top;">
                        <div style="border-bottom:2px solid #059669;width:100px;height:30px;margin:0 auto;"></div>
                        <p style="font-size:9px;color:#374151;margin:5px 0 0 0;">المدير العام</p>
                        <p style="font-size:8px;color:#6b7280;margin:2px 0;">الاسم: ........................</p>
                        <p style="font-size:8px;color:#6b7280;margin:2px 0;">التوقيع: ....................</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:8px;margin-top:15px;text-align:center;">
            <p style="font-size:8px;color:#64748b;margin:0;">Jawda HR - نظام إدارة الموارد البشرية | تاريخ الطباعة: ' . now()->format('Y-m-d H:i') . '</p>
        </div>
        ';
        
        return $html;
    }

    public static function getOrganizationData()
    {
        $org = \App\Models\Setting::where('key', 'organization')->first();
        return $org ? array_merge($org->value ?? [], [
            'logo' => $org->value['logo'] ?? null,
            'stamp' => $org->value['stamp'] ?? null,
        ]) : [
            'name' => 'Jawda HR',
            'address' => '',
            'phone' => '',
            'currency_symbol' => 'جنيه',
        ];
    }
}
