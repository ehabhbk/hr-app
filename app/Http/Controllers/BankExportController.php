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

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Jawda HR');
        $pdf->SetAuthor($orgData['name'] ?? 'HR System');
        $pdf->SetTitle("كشف تحويل مرتبات - {$bankName}");
        $pdf->setRTL(true);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Header
        $pdf->SetFont('aealarabiya', '', 20);
        $pdf->SetTextColor(30, 58, 95);
        $pdf->Cell(0, 12, "كشف تحويل مرتبات - {$bankName}", 0, 1, 'C');
        $pdf->SetFont('aealarabiya', '', 14);
        $pdf->SetTextColor(71, 85, 105);
        $orgName = $orgData['name'] ?? '';
        if ($orgName) {
            $pdf->Cell(0, 8, $orgName, 0, 1, 'C');
        }
        $pdf->Cell(0, 8, "الشهر: {$monthLabel} {$year}", 0, 1, 'C');
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetFont('aealarabiya', '', 10);
        $pdf->Cell(0, 6, "تاريخ الطباعة: " . now()->format('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(3);

        // Separator line
        $pdf->SetDrawColor(30, 58, 95);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(5, $pdf->GetY(), 292, $pdf->GetY());
        $pdf->Ln(5);

        // Table columns
        $rowH = 10;
        $headerH = 12;
        $cols = [8, 60, 40, 40, 58, 65];
        $headerLabels = ['#', 'اسم الموظف', 'الوظيفة', 'القسم', 'رقم الحساب', 'صافي المرتب'];

        $bottomMargin = 10;
        $pageHeight = $pdf->getPageHeight();

        $drawHeader = function() use ($pdf, $cols, $headerLabels, $headerH) {
            $pdf->SetFillColor(30, 58, 95);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('aealarabiya', '', 14);
            for ($i = 0; $i < count($headerLabels); $i++) {
                $pdf->Cell($cols[$i], $headerH, $headerLabels[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
        };

        $drawHeader();

        $calculatedTotal = 0;
        $idx = 1;
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('aealarabiya', '', 13);

        foreach ($employees as $emp) {
            // Page break check
            if ($pdf->GetY() + $rowH > $pageHeight - $bottomMargin) {
                $pdf->AddPage();
                $pdf->SetFont('aealarabiya', '', 18);
                $pdf->SetTextColor(30, 58, 95);
                $pdf->Cell(0, 10, "كشف تحويل مرتبات - {$bankName} (تابع)", 0, 1, 'C');
                $pdf->SetTextColor(71, 85, 105);
                $pdf->SetFont('aealarabiya', '', 10);
                $pdf->Cell(0, 6, $orgName, 0, 1, 'C');
                $pdf->Ln(2);
                $drawHeader();
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('aealarabiya', '', 13);
            }

            $s = $salaryMap[$emp->id] ?? ['net_salary' => 0];
            $netSalary = (float)($s['net_salary'] ?? 0);
            $calculatedTotal += $netSalary;

            // Alternating row colors
            if ($idx % 2 === 0) {
                $pdf->SetFillColor(248, 250, 252);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->Cell($cols[0], $rowH, (string)$idx, 1, 0, 'C', true);
            $pdf->Cell($cols[1], $rowH, $emp->name ?? '-', 1, 0, 'C', true);
            $pdf->Cell($cols[2], $rowH, $emp->position ?? '-', 1, 0, 'C', true);
            $pdf->Cell($cols[3], $rowH, $emp->department->name ?? '-', 1, 0, 'C', true);
            $pdf->Cell($cols[4], $rowH, $emp->bank_account ?? '-', 1, 0, 'C', true);
            $pdf->Cell($cols[5], $rowH, number_format($netSalary, 2) . ' ج.س', 1, 0, 'C', true);
            $pdf->Ln();
            $idx++;
        }

        // Total row
        $pdf->SetFont('aealarabiya', 'B', 14);
        $pdf->SetFillColor(239, 246, 255);
        $pdf->SetTextColor(22, 163, 74);
        $pdf->Cell($cols[0] + $cols[1] + $cols[2] + $cols[3], 10, 'الإجمالي', 1, 0, 'C', true);
        $pdf->Cell($cols[4], 10, '', 1, 0, 'C', true);
        $pdf->Cell($cols[5], 10, number_format($calculatedTotal, 2) . ' ج.س', 1, 0, 'C', true);

        $filename = "كشف_تحويل_{$export->bank_name}_{$monthLabel}_{$year}.pdf";
        $pdfContent = $pdf->Output($filename, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
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
