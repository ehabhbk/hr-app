<?php

namespace App\Http\Controllers;

use App\Models\BankExport;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BankExportController extends Controller
{
    public function index(Request $request)
    {
        $exports = BankExport::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['data' => $exports]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'bank_name' => 'required|string',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer',
        ]);

        $result = BankExport::generateForBank(
            $request->month,
            $request->year,
            $request->bank_name,
            $request->employee_ids
        );

        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];

        $export = $result['export'];
        $records = $result['records'];

        $content = $this->generateBankFile($export, $records, $orgData);

        $filename = "bank_export_{$export->bank_name}_{$export->year}_{$export->month}_" . time() . ".txt";
        
        if (!Storage::exists('public/bank_exports')) {
            Storage::makeDirectory('public/bank_exports');
        }
        
        Storage::put("public/bank_exports/{$filename}", $content);

        $export->update([
            'file_path' => "bank_exports/{$filename}",
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        return response()->json([
            'data' => $export,
            'records' => $records,
            'download_url' => asset('storage/' . $export->file_path),
        ]);
    }

    private function generateBankFile($export, $records, $orgData)
    {
        $bankName = BankExport::getBankNameArabic($export->bank_name);
        $lines = [];

        $lines[] = "==================================================";
        $lines[] = "           كشف تحويل المرتبات البنكي";
        $lines[] = "==================================================";
        $lines[] = "";
        $lines[] = "اسم المؤسسة: " . ($orgData['name'] ?? 'N/A');
        $lines[] = "الرقم الضريبي: " . ($orgData['tax_number'] ?? 'N/A');
        $lines[] = "السجل التجاري: " . ($orgData['commercial_register'] ?? 'N/A');
        $lines[] = "اسم البنك: " . $bankName;
        $lines[] = "الشهر: " . Carbon::create($export->year, $export->month, 1)->locale('ar')->monthName . " " . $export->year;
        $lines[] = "تاريخ الطباعة: " . now()->format('Y-m-d H:i:s');
        $lines[] = "عدد الموظفين: " . $export->employee_count;
        $lines[] = "إجمالي المبلغ: " . number_format($export->total_amount, 2) . " جنيه سوداني";
        $lines[] = "";
        $lines[] = "--------------------------------------------------";
        $lines[] = "| #  | الاسم                    | رقم الحساب      | المبلغ         |";
        $lines[] = "--------------------------------------------------";

        $lineNum = 1;
        foreach ($records as $record) {
            $lines[] = sprintf(
                "| %2d  | %-24s | %-15s | %s |",
                $lineNum,
                mb_substr($record['name'], 0, 24),
                $record['bank_account'],
                number_format($record['amount'], 2)
            );
            $lineNum++;
        }

        $lines[] = "--------------------------------------------------";
        $lines[] = "";
        $lines[] = "الإجمالي الكلي: " . number_format($export->total_amount, 2) . " جنيه سوداني";
        $lines[] = "";
        $lines[] = "==================================================";
        $lines[] = "";
        $lines[] = "التفاصيل:";
        $lines[] = "- الراتب الأساسي + البدلات + الحوافز - التأمين";
        $lines[] = "";
        $lines[] = "توقيع المدير المالي: ________________________";
        $lines[] = "توقيع المدير العام: ________________________";
        $lines[] = "الختم:";
        $lines[] = "==================================================";

        return implode("\n", $lines);
    }

    public function download($id)
    {
        $export = BankExport::findOrFail($id);

        if (!$export->file_path || !Storage::exists("public/{$export->file_path}")) {
            return response()->json(['error' => 'الملف غير موجود'], 404);
        }

        return Storage::download("public/{$export->file_path}");
    }

    public function downloadPdf($id)
    {
        $export = BankExport::findOrFail($id);
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];

        $pdfCfgRow = Setting::where('key', 'pdf_settings')->first();
        $pdfCfg = $pdfCfgRow ? $pdfCfgRow->value : [];
        $mt = (int)($pdfCfg['margin_top'] ?? 15);
        $mb = (int)($pdfCfg['margin_bottom'] ?? 15);
        $ml = (int)($pdfCfg['margin_left'] ?? 15);
        $mr = (int)($pdfCfg['margin_right'] ?? 15);
        $fontSize = (int)($pdfCfg['font_size'] ?? 12);
        $lineH = (int)($pdfCfg['line_height'] ?? 2);

        $showHeader = $pdfCfg['show_header'] !== false;
        $showStamp = $pdfCfg['show_stamp'] !== false;
        $showSignatures = $pdfCfg['show_signatures'] !== false;
        $showGM = ($pdfCfg['show_gm_signature'] ?? true) !== false;
        $showFinance = ($pdfCfg['show_finance_signature'] ?? false) !== false;
        $gmTitle = $pdfCfg['gm_title'] ?? 'المدير العام';
        $financeTitle = $pdfCfg['finance_title'] ?? 'المدير المالي';
        $gmName = $orgData['general_manager_name'] ?? '';
        $financeName = $orgData['finance_manager_name'] ?? '';
        $logoSize = (int)($pdfCfg['logo_width'] ?? 55);
        $stampSize = (int)($pdfCfg['stamp_width'] ?? 55);
        $logoPosition = $pdfCfg['logo_position'] ?? 'center';

        $month = (int)$export->month;
        $year = (int)$export->year;
        $ids = $export->employee_ids ?? [];
        $employees = Employee::whereIn('id', $ids)
            ->with(ReportsController::salaryRelations($month, $year))
            ->get();
        $salaryMap = [];
        foreach ($employees as $emp) {
            $salaryMap[$emp->id] = ReportsController::computeEmployeeSalary($emp, $month, $year);
        }
        $monthNames = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        $monthLabel = $monthNames[$month - 1] ?? $month;
        $bankName = BankExport::getBankNameArabic($export->bank_name);

        $rows = [];
        $calculatedTotal = 0;
        $idx = 1;
        foreach ($employees as $emp) {
            $s = $salaryMap[$emp->id] ?? ['net_salary' => 0];
            $netSalary = (float)($s['net_salary'] ?? 0);
            $calculatedTotal += $netSalary;
            $rows[] = [
                'idx' => $idx++,
                'name' => $emp->name ?? '-',
                'position' => $emp->position ?? '-',
                'department' => $emp->department->name ?? '-',
                'bank_account' => $emp->bank_account ?? '-',
                'net_salary' => number_format($netSalary, 2),
            ];
        }

        $orgName = $orgData['name'] ?? '';

        $sigImg = function ($path, $size = 80) {
            if (!$path || !file_exists($path)) return '';
            $b64 = base64_encode(file_get_contents($path));
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            return '<img src="data:' . $mime . ';base64,' . $b64 . '" style="height:' . $size . 'px;object-fit:contain;">';
        };

        $logoHtml = '';
        $logoPath = !empty($orgData['logo']) ? public_path('storage/' . $orgData['logo']) : null;
        if ($logoPath && file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoData . '" style="height:' . $logoSize . 'px;width:' . $logoSize . 'px;object-fit:contain;">';
        }

        $stampHtml = '';
        $stampPath = !empty($orgData['stamp']) ? public_path('storage/' . $orgData['stamp']) : null;
        if ($stampPath && file_exists($stampPath)) {
            $stampData = base64_encode(file_get_contents($stampPath));
            $stampMime = mime_content_type($stampPath);
            $stampHtml = '<img src="data:' . $stampMime . ';base64,' . $stampData . '" style="height:' . $stampSize . 'px;width:' . $stampSize . 'px;object-fit:contain;opacity:0.85;">';
        }

        $gmSigPath = !empty($orgData['gm_signature']) ? public_path('storage/' . $orgData['gm_signature']) : null;
        $financeSigPath = !empty($orgData['finance_signature']) ? public_path('storage/' . $orgData['finance_signature']) : null;

        $logoAlign = $logoPosition === 'right' ? 'right' : ($logoPosition === 'left' ? 'left' : 'center');
        $logoPlaceholder = '<div style="width:' . $logoSize . 'px;height:' . $logoSize . 'px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#6366f1;font-size:9px;font-weight:bold;">شعار</div>';
        $stampPlaceholder = '<div style="width:' . $stampSize . 'px;height:' . $stampSize . 'px;border:1.5px dashed #cbd5e1;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div>';

        $html = '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><style>
            @page { margin: ' . $mt . 'mm ' . $mr . 'mm ' . $mb . 'mm ' . $ml . 'mm; }
            body { font-family: "Amiri", "DejaVu Sans", sans-serif; margin: 0; padding: 0; direction: rtl; font-size: ' . $fontSize . 'px; line-height: ' . $lineH . '; }
            h1 { text-align: center; color: #1e3a5f; font-size: ' . ($fontSize + 8) . 'px; margin: 5px 0; }
            .org { text-align: center; color: #475569; font-size: ' . $fontSize . 'px; margin: 2px 0; }
            .date { text-align: center; color: #64748b; font-size: 10px; margin: 2px 0 8px 0; }
            .line { border-top: 2px solid #1e3a5f; margin: 8px 0; }
            table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.data-table th { background: #1e3a5f; color: white; padding: 8px 5px; border: 1px solid #1e3a5f; font-size: 12px; text-align: center; }
            table.data-table td { padding: 6px 5px; border: 1px solid #d1d5db; font-size: 11px; text-align: center; direction: rtl; }
            table.data-table tr:nth-child(even) { background: #f8fafc; }
            table.data-table .total td { background: #eff6ff; color: #16a34a; font-weight: bold; font-size: 12px; }
        </style></head><body>';

        if ($showHeader) {
            $html .= '<table style="width:100%;border:none;border-collapse:collapse;margin-bottom:10px;">
                <tr>
                    <td style="width:' . ($logoSize + 10) . 'px;text-align:center;vertical-align:middle;">
                        <div style="text-align:' . $logoAlign . ';">' . ($logoHtml ?: $logoPlaceholder) . '</div>
                    </td>
                    <td style="text-align:center;padding:5px 10px;vertical-align:middle;">
                        <h1>' . htmlspecialchars($orgName) . '</h1>
                        <div class="org">كشف تحويل مرتبات - ' . htmlspecialchars($bankName) . '</div>
                        ' . ($orgData['address'] ? '<div class="date">العنوان: ' . htmlspecialchars($orgData['address']) . '</div>' : '') . '
                    </td>
                    <td style="width:' . ($logoSize + 10) . 'px;"></td>
                </tr>
            </table>
            <div style="height:3px;background:linear-gradient(90deg, #1e3a5f, #3b82f6, #6366f1, #3b82f6, #1e3a5f);margin:8px 0 15px 0;border-radius:2px;"></div>';
        } else {
            $html .= '<h1>كشف تحويل مرتبات - ' . htmlspecialchars($bankName) . '</h1>';
            if ($orgName) {
                $html .= '<div class="org">' . htmlspecialchars($orgName) . '</div>';
            }
        }

        $html .= '<div class="org">الشهر: ' . $monthLabel . ' ' . $year . '</div>';
        $html .= '<div class="date">تاريخ الطباعة: ' . now()->format('Y-m-d H:i:s') . '</div>';
        $html .= '<div class="line"></div>';

        $html .= '<table class="data-table"><thead><tr>
            <th>#</th><th>اسم الموظف</th><th>الوظيفة</th><th>القسم</th><th>رقم الحساب</th><th>صافي المرتب</th>
        </tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>
                <td>' . $row['idx'] . '</td>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td>' . htmlspecialchars($row['position']) . '</td>
                <td>' . htmlspecialchars($row['department']) . '</td>
                <td>' . htmlspecialchars($row['bank_account']) . '</td>
                <td>' . $row['net_salary'] . ' ج.س</td>
            </tr>';
        }

        $html .= '<tr class="total">
            <td colspan="5">الإجمالي</td>
            <td>' . number_format($calculatedTotal, 2) . ' ج.س</td>
        </tr>';
        $html .= '</tbody></table>';

        if ($showStamp && $stampHtml) {
            $html .= '<div style="text-align:center;margin-top:15px;">' . ($stampHtml ?: $stampPlaceholder) . '<div style="font-size:8px;color:#64748b;">ختم المؤسسة</div></div>';
        }

        if ($showSignatures && ($showGM || $showFinance)) {
            $sigCells = '';
            if ($showFinance) {
                $sigCells .= '<td style="width:35%;text-align:center;vertical-align:top;padding:8px;">
                    <div style="min-height:50px;display:flex;align-items:center;justify-content:center;">' . ($financeSigPath ? $sigImg($financeSigPath) : '<div style="border-bottom:1.5px solid #1e3a5f;width:120px;"></div>') . '</div>
                    <p style="font-size:10px;font-weight:bold;color:#1e3a5f;">' . htmlspecialchars($financeTitle) . '</p>
                    ' . ($financeName ? '<p style="font-size:8px;color:#333;margin:2px 0;">' . htmlspecialchars($financeName) . '</p>' : '<p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>') . '
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                </td>';
            }
            if ($showGM) {
                $sigCells .= '<td style="width:35%;text-align:center;vertical-align:top;padding:8px;">
                    <div style="min-height:50px;display:flex;align-items:center;justify-content:center;">' . ($gmSigPath ? $sigImg($gmSigPath) : '<div style="border-bottom:1.5px solid #059669;width:120px;"></div>') . '</div>
                    <p style="font-size:10px;font-weight:bold;color:#059669;">' . htmlspecialchars($gmTitle) . '</p>
                    ' . ($gmName ? '<p style="font-size:8px;color:#333;margin:2px 0;">' . htmlspecialchars($gmName) . '</p>' : '<p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>') . '
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                </td>';
            }
            $html .= '<table style="width:100%;border:none;margin-top:20px;"><tr>' . $sigCells . '</tr></table>';
        }

        $html .= '</body></html>';

        ob_start();
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        ob_end_clean();

        $pdf->SetCreator('Jawda HR');
        $pdf->SetAuthor($orgName ?: 'Jawda HR');
        $pdf->SetTitle('كشف تحويل مرتبات - ' . $bankName);
        $pdf->SetSubject('كشف تحويل مرتبات');

        $pdf->setRTL(true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetAutoPageBreak(true, 25);

        $pdf->AddPage();

        ob_start();
        $pdf->writeHTML($html, true, false, true, false, 'R');
        ob_end_clean();

        $pdfContent = $pdf->Output('bank_export.pdf', 'S');

        $filename = "كشف_تحويل_{$export->bank_name}_{$monthLabel}_{$year}.pdf";

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename*=UTF-8\'\'' . rawurlencode($filename))
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }

    public function getBanks()
    {
        return response()->json([
            'banks' => [
                ['key' => 'فهد', 'name' => 'بنك الفهد الإسلامي', 'icon' => '🏦'],
                ['key' => 'التعاون', 'name' => 'بنك التعاون', 'icon' => '🤝'],
                ['key' => 'الزراعي', 'name' => 'البنك الزراعي', 'icon' => '🌾'],
                ['key' => 'الشعب', 'name' => 'بنك الشعب', 'icon' => '👥'],
                ['key' => 'الثقة', 'name' => 'بنك الثقة', 'icon' => '✓'],
                ['key' => 'الخرطوم', 'name' => 'بنك الخرطوم', 'icon' => '🏛️'],
                ['key' => 'فيصل', 'name' => 'بنك فيصل الإسلامي', 'icon' => '☪️'],
                ['key' => 'السودان', 'name' => 'بنك السودان', 'icon' => '🇸🇩'],
                ['key' => 'طيبة', 'name' => 'بنك طيبة الإسلامي', 'icon' => '🕌'],
                ['key' => 'الدوحة', 'name' => 'بنك الدوحة', 'icon' => '🏙️'],
                ['key' => 'التأمين', 'name' => 'شركة التأمين', 'icon' => '🛡️'],
                ['key' => 'اخرى', 'name' => 'بنوك أخرى', 'icon' => '💼'],
            ]
        ]);
    }

    public function destroy($id)
    {
        $export = BankExport::findOrFail($id);
        
        if ($export->file_path && Storage::exists("public/{$export->file_path}")) {
            Storage::delete("public/{$export->file_path}");
        }
        
        $export->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
