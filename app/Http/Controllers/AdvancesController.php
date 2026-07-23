<?php

namespace App\Http\Controllers;

use App\Models\AdvanceRequest;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class AdvancesController extends Controller
{
    public function index()
    {
        $advances = AdvanceRequest::with('employee')->orderBy('created_at', 'desc')->get()->map(function ($a) {
            $a->attachment_url = $a->attachment ? url('storage/' . $a->attachment) : null;
            $a->paid_installments_count = $a->paid_installments_count;
            $a->total_paid_amount = $a->total_paid_amount;
            $a->total_remaining_amount = $a->total_remaining_amount;
            return $a;
        });
        return response()->json(['data' => $advances]);
    }

    public function store(Request $r)
    {
        // Decode JSON string from multipart form data
        if ($r->has('installments_detail') && is_string($r->input('installments_detail'))) {
            $decoded = json_decode($r->input('installments_detail'), true);
            if (is_array($decoded)) {
                $r->merge(['installments_detail' => $decoded]);
            }
        }

        $data = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|string|in:short,long',
            'installments' => 'nullable|integer|min:1',
            'installments_detail' => 'nullable|array',
            'installments_detail.*.amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $employee = Employee::find($data['employee_id']);
        if (!$employee) {
            return response()->json(['message' => 'الموظف غير موجود'], 404);
        }

        // Check employee status
        if ($employee->status === 'terminated') {
            return response()->json([
                'message' => 'عذراً، لا يمكن لهذا الموظف طلب سلفة لأنه مفصول',
                'error' => 'employee_terminated'
            ], 422);
        }

        $type = $data['type'];

        // Get advance settings
        $advanceSettings = \App\Models\Setting::where('key', 'advances')->first();
        $allSettings = $advanceSettings ? $advanceSettings->value : [];
        
        $shortSettings = $allSettings['short_advance'] ?? [
            'enabled' => true,
            'max_percent' => 50,
            'min_service_months' => 0,
        ];
        
        $longSettings = $allSettings['long_advance'] ?? [
            'enabled' => true,
            'max_amount' => 500000,
            'min_amount' => 10000,
            'min_service_months' => 6,
            'max_installments' => 12,
            'min_installments' => 3,
        ];

        // Check if advances are enabled
        if (!($allSettings['enabled'] ?? true)) {
            return response()->json([
                'message' => 'السلف غير مفعلة حالياً',
                'error' => 'advances_disabled'
            ], 422);
        }

        // Get settings based on type
        $typeSettings = $type === 'short' ? $shortSettings : $longSettings;
        
        // Check if this type is enabled
        if (!($typeSettings['enabled'] ?? true)) {
            return response()->json([
                'message' => $type === 'short' ? 'السلف القصيرة غير مفعلة حالياً' : 'السلف الطويلة غير مفعلة حالياً',
                'error' => 'advance_type_disabled'
            ], 422);
        }

        // Calculate max advance amount
        $baseSalary = (float) ($employee->base_salary ?? 0);
        $positionAllowance = (float) ($employee->position_allowance ?? 0);
        $grossSalary = $baseSalary + $positionAllowance;

        if ($type === 'short') {
            $maxPercent = $shortSettings['max_percent'] ?? 50;
            $maxAmount = ($grossSalary * $maxPercent) / 100;
        } else {
            $maxAmount = $longSettings['max_amount'] ?? 500000;
        }

        // Check minimum service months (not required for short advances)
        if ($type === 'long' && $employee && $employee->hire_date) {
            $hireDate = new \DateTime($employee->hire_date);
            $now = new \DateTime();
            $serviceYears = $hireDate->diff($now)->y;
            $serviceMonths = $hireDate->diff($now)->m;
            $totalMonths = ($serviceYears * 12) + $serviceMonths;
            $minMonths = $longSettings['min_service_months'] ?? 6;

            if ($totalMonths < $minMonths) {
                return response()->json([
                    'message' => "يجب أن تكون مدة خدمتك {$minMonths} أشهر على الأقل. مدة خدمتك الحالية: {$totalMonths} شهر",
                    'error' => 'insufficient_service'
                ], 422);
            }
        }

        // Check minimum and maximum amounts
        $minAmount = $longSettings['min_amount'] ?? 10000;
        if ($type === 'long' && $data['amount'] < $minAmount) {
            return response()->json([
                'message' => "الحد الأدنى للسلفة الطويلة هو {$minAmount} ج.س",
                'error' => 'amount_below_min'
            ], 422);
        }

        if ($data['amount'] > $maxAmount) {
            $msg = $type === 'short'
                ? "الحد الأقصى للسلفة {$maxPercent}% من إجمالي المرتب = {$maxAmount} ج.س"
                : "الحد الأقصى للسلفة {$maxAmount} ج.س";
            return response()->json([
                'message' => $msg,
                'error' => 'amount_exceeds_max'
            ], 422);
        }

        // Check if employee has pending advance request
        $pendingAdvance = AdvanceRequest::where('employee_id', $data['employee_id'])
            ->where('status', 'pending')
            ->first();

        if ($pendingAdvance) {
            return response()->json([
                'message' => 'لديك طلب سلفة قيد الانتظار مسبقاً. يرجى انتظار الموافقة أو إلغاء الطلب السابق.',
                'error' => 'pending_advance_exists'
            ], 422);
        }

        // Check if employee has active repayment of same type
        $activeRepayment = AdvanceRequest::where('employee_id', $data['employee_id'])
            ->where('status', 'approved')
            ->where('type', $type)
            ->where('remaining_amount', '>', 0)
            ->first();

        if ($activeRepayment) {
            return response()->json([
                'message' => 'لديك سلفة ' . ($type === 'short' ? 'قصيرة' : 'طويلة') . ' قيد السداد. يرجى تسديدها أولاً.',
                'error' => 'active_repayment_exists'
            ], 422);
        }

        // Set installments
        $installments = $data['installments'] ?? 1;
        if ($type === 'long') {
            $minInstallments = $longSettings['min_installments'] ?? 3;
            $maxInstallments = $longSettings['max_installments'] ?? 12;
            $installments = max($minInstallments, min($installments, $maxInstallments));
        }

        // For long advances: validate installments_detail
        $installmentsDetail = null;
        if ($type === 'long') {
            if (empty($data['installments_detail']) || count($data['installments_detail']) !== $installments) {
                return response()->json([
                    'message' => "يرجى إدخال {$installments} قسط بقيمة كل قسط",
                    'error' => 'installments_detail_required'
                ], 422);
            }

            $sumAmounts = array_sum(array_column($data['installments_detail'], 'amount'));
            if (abs($sumAmounts - (float)$data['amount']) > 0.01) {
                return response()->json([
                    'message' => 'مجموع الأقساط (' . number_format($sumAmounts) . ') يجب أن يساوي قيمة السلفة (' . number_format($data['amount']) . ')',
                    'error' => 'installments_sum_mismatch'
                ], 422);
            }

            if ($grossSalary > 0) {
                foreach ($data['installments_detail'] as $i => $inst) {
                    if ((float)$inst['amount'] > $grossSalary) {
                        return response()->json([
                            'message' => "القسط رقم " . ($i + 1) . " قيمته {$inst['amount']} أكبر من المرتب الشهري ({$grossSalary})",
                            'error' => 'installment_exceeds_salary'
                        ], 422);
                    }
                }
            }

            $now = now();
            $installmentsDetail = [];
            foreach ($data['installments_detail'] as $i => $inst) {
                $dt = $now->copy()->addMonths($i);
                $installmentsDetail[] = [
                    'installment_no' => $i + 1,
                    'amount' => (float)$inst['amount'],
                    'month' => $dt->month,
                    'year' => $dt->year,
                    'paid' => false,
                    'paid_at' => null,
                ];
            }
        }

        // Handle file upload
        $attachmentPath = null;
        if ($r->hasFile('attachment')) {
            $attachmentPath = $r->file('attachment')->store('advance-attachments', 'public');
        }

        $advanceData = [
            'employee_id' => $data['employee_id'],
            'amount' => $data['amount'],
            'type' => $type,
            'installments' => $installments,
            'note' => $data['note'] ?? null,
            'attachment' => $attachmentPath,
            'remaining_amount' => $data['amount'],
            'status' => 'pending',
        ];
        if ($installmentsDetail) {
            $advanceData['installments_detail'] = $installmentsDetail;
        }

        $advance = AdvanceRequest::create($advanceData);

        if ($employee) {
            Notification::send(
                auth()->id(),
                'salary',
                'طلب سلفة جديد',
                "تم تقديم طلب سلفة من {$employee->name}: " . number_format($data['amount']),
                ['advance_id' => $advance->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $advance], 201);
    }

    public function approve($id)
    {
        $a = AdvanceRequest::findOrFail($id);
        $a->status = 'approved';
        $a->date = now()->format('Y-m-d');
        
        // Initialize installments_detail if not set (for long advances)
        if ($a->type === 'long' && $a->installments > 0) {
            $a->monthly_installment = $a->amount / $a->installments;
            $a->remaining_amount = $a->amount;
            if (empty($a->installments_detail)) {
                $now = now();
                $detail = [];
                for ($i = 0; $i < $a->installments; $i++) {
                    $dt = $now->copy()->addMonths($i);
                    $detail[] = [
                        'installment_no' => $i + 1,
                        'amount' => $a->monthly_installment,
                        'month' => $dt->month,
                        'year' => $dt->year,
                        'paid' => false,
                        'paid_at' => null,
                    ];
                }
                $a->installments_detail = $detail;
            }
        }
        
        $a->save();

        $employee = Employee::find($a->employee_id);
        $whatsapp = new WhatsAppService();

        if ($employee) {
            $employee->advance = ($employee->advance ?? 0) + $a->amount;
            $employee->save();

            $whatsapp->sendAdvanceNotification($employee, $a, 'approved');

            // Admin notification
            try {
                $whatsapp->notifyAdminAdvance($employee, $a, 'approved');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
            }

            Notification::send(
                auth()->id(),
                'salary',
                'تمت الموافقة على السلفة',
                "تمت الموافقة على سلفة {$employee->name}: " . number_format($a->amount),
                ['advance_id' => $a->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $a]);
    }

    public function reject($id)
    {
        $a = AdvanceRequest::findOrFail($id);
        $a->status = 'rejected';
        $a->save();

        $employee = Employee::find($a->employee_id);
        $whatsapp = new WhatsAppService();

        if ($employee) {
            $whatsapp->sendAdvanceNotification($employee, $a, 'rejected');

            // Admin notification
            try {
                $whatsapp->notifyAdminAdvance($employee, $a, 'rejected');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp admin notification error: ' . $e->getMessage());
            }

            Notification::send(
                auth()->id(),
                'salary',
                'تم رفض السلفة',
                "تم رفض سلفة {$employee->name}: " . number_format($a->amount),
                ['advance_id' => $a->id, 'employee_id' => $employee->id]
            );
        }

        return response()->json(['data' => $a]);
    }

    public function payInstallment(Request $r, $id)
    {
        $a = AdvanceRequest::findOrFail($id);
        if ($a->status !== 'approved') {
            return response()->json(['message' => 'السلفة غير معتمدة'], 422);
        }

        $installmentNo = $r->input('installment_no');
        $detail = $a->installments_detail ?? [];

        if (empty($detail)) {
            return response()->json(['message' => 'لا توجد أقساط مسجلة'], 422);
        }

        $found = false;
        foreach ($detail as &$inst) {
            if ((int)$inst['installment_no'] === (int)$installmentNo && !$inst['paid']) {
                $inst['paid'] = true;
                $inst['paid_at'] = now()->toDateTimeString();
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json(['message' => 'القسط غير موجود أو مدفوع مسبقاً'], 422);
        }

        $a->installments_detail = $detail;
        $a->syncFromDetail();
        $a->save();

        return response()->json(['data' => $a]);
    }
}
