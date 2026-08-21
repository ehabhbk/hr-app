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

        $monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
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

        $html = '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><style>
            body { font-family: "DejaVu Sans", "Noto Sans Arabic", Arial, sans-serif; margin: 0; padding: 15px; direction: rtl; }
            h1 { text-align: center; color: #1e3a5f; font-size: 22px; margin: 0 0 5px 0; }
            .org { text-align: center; color: #475569; font-size: 16px; margin: 2px 0; }
            .date { text-align: center; color: #64748b; font-size: 11px; margin: 2px 0 8px 0; }
            .line { border-top: 2px solid #1e3a5f; margin: 8px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #1e3a5f; color: white; padding: 10px 6px; border: 1px solid #1e3a5f; font-size: 14px; text-align: center; }
            td { padding: 8px 6px; border: 1px solid #d1d5db; font-size: 13px; text-align: center; }
            tr:nth-child(even) { background: #f8fafc; }
            .total td { background: #eff6ff; color: #16a34a; font-weight: bold; font-size: 14px; }
            .footer { text-align: center; color: #94a3b8; font-size: 10px; margin-top: 15px; }
        </style></head><body>
            <h1>كشف تحويل مرتبات - ' . htmlspecialchars($bankName) . '</h1>';
        if ($orgName) {
            $html .= '<div class="org">' . htmlspecialchars($orgName) . '</div>';
        }
        $html .= '<div class="org">الشهر: ' . $monthLabel . ' ' . $year . '</div>';
        $html .= '<div class="date">تاريخ الطباعة: ' . now()->format('Y-m-d H:i:s') . '</div>';
        $html .= '<div class="line"></div>';
        $html .= '<table><thead><tr>
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

        $html .= '</tbody></table></body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHtml($html)
            ->setPaper('a4', 'landscape')
            ->setOrientation('landscape');

        $filename = "كشف_تحويل_{$export->bank_name}_{$monthLabel}_{$year}.pdf";

        return $pdf->download($filename);
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
