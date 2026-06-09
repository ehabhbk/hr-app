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
