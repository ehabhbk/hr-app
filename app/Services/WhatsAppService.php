<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $apiUrl;
    private $apiKey;
    private $enabled;
    private $notifyOnWarning = true;
    private $notifyOnLeave = true;
    private $notifyOnAdvance = true;
    private $notifyOnSalary = true;
    private $notifyOnLate = true;
    private $notifyPhone = '';

    public function __construct()
    {
        $settings = Setting::where('key', 'whatsapp')->first();
        $data = $settings ? $settings->value : [];
        
        $this->apiUrl = $data['api_url'] ?? 'https://api.whatsapp.com/send';
        $this->apiKey = $data['api_key'] ?? '';
        $this->enabled = $data['enabled'] ?? false;
        $this->notifyPhone = $data['notify_phone'] ?? '';
        $this->notifyOnWarning = $data['notify_on_warning'] ?? true;
        $this->notifyOnLeave = $data['notify_on_leave'] ?? true;
        $this->notifyOnAdvance = $data['notify_on_advance'] ?? true;
        $this->notifyOnSalary = $data['notify_on_salary'] ?? true;
        $this->notifyOnLate = $data['notify_on_late'] ?? true;
    }

    public function isEnabled()
    {
        return $this->enabled && !empty($this->apiKey);
    }

    public function sendMessage($phone, $message)
    {
        if (!$this->isEnabled()) {
            Log::info('WhatsApp disabled or no API key');
            return false;
        }

        try {
            $phone = $this->formatPhone($phone);
            
            $response = Http::timeout(30)->post($this->apiUrl, [
                'phone' => $phone,
                'message' => $message,
                'access_token' => $this->apiKey,
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp message sent to {$phone}");
                return true;
            }

            Log::error("WhatsApp API error: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("WhatsApp error: " . $e->getMessage());
            return false;
        }
    }

    public function sendToEmployee($employeeId, $message)
    {
        $employee = Employee::find($employeeId);
        if (!$employee || !$employee->phone) {
            return false;
        }
        return $this->sendMessage($employee->phone, $message);
    }

    public function sendWarningNotification($employee, $warning)
    {
        if (!$this->notifyOnWarning) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "⚠️ {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "لقد صدر لك {$warning->type} بسبب: {$warning->reason}\n";
        $message .= "التاريخ: {$warning->date}\n\n";
        $message .= "نأمل الالتزام بالأنظمة والتعليمات.\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendLeaveNotification($employee, $leave, $status)
    {
        if (!$this->notifyOnLeave) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $statusText = match($status) {
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
            'pending' => 'قيد المراجعة',
            default => $status,
        };
        
        $message = "🏖️ {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "طلب الإجازة:\n";
        $message .= "النوع: {$leave->type}\n";
        $message .= "من: {$leave->start_date}\n";
        $message .= "إلى: {$leave->end_date}\n";
        $message .= "الحالة: {$statusText}\n\n";
        
        if ($status === 'approved') {
            $message .= "نرجو الاستمتاع بإجازتكم والعودة في الموعد المحدد.\n";
        } elseif ($status === 'rejected') {
            $message .= "يرجى التواصل مع قسم الموارد البشرية للاستفسار.\n";
        }
        
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendLeaveReminder($employee, $leave)
    {
        if (!$this->notifyOnLeave) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "🔔 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "تذكير: تنتهي إجازتك غداً ({$leave->end_date})\n\n";
        $message .= "يرجى التأكد من العودة للعمل في الموعد المحدد.\n\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendAdvanceNotification($employee, $advance, $status)
    {
        if (!$this->notifyOnAdvance) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $statusText = match($status) {
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
            default => $status,
        };
        
        $message = "💰 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "طلب السلفة:\n";
        $message .= "المبلغ: " . number_format($advance->amount) . " جنيه سوداني\n";
        $message .= "الحالة: {$statusText}\n\n";
        
        if ($status === 'approved') {
            $message .= "سيتم تحويل المبلغ إلى حسابك قريباً.\n";
        }
        
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendLateArrivalNotification($employee, $date, $lateMinutes, $deductionAmount = 0)
    {
        if (!$this->notifyOnLate) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "⏰ {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "تم تسجيل تأخير في الحضور:\n";
        $message .= "التاريخ: {$date}\n";
        $message .= "مدة التأخير: {$lateMinutes} دقيقة\n";
        if ($deductionAmount > 0) {
            $message .= "المبلغ المخصوم: " . number_format($deductionAmount, 2) . " جنيه سوداني\n";
        }
        $message .= "\nنأمل الالتزام بمواعيد العمل.\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendSalaryIncreaseNotification($employee, $increase)
    {
        if (!$this->notifyOnSalary) {
            return false;
        }

        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "🎉 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "يسرنا إخطاركم بزيادة المرتب:\n";
        $message .= "الراتب القديم: " . number_format($increase->old_salary) . " جنيه\n";
        $message .= "الراتب الجديد: " . number_format($increase->new_salary) . " جنيه\n";
        $message .= "نسبة الزيادة: {$increase->increase_percent}%\n";
        $message .= "تاريخ التطبيق: {$increase->effective_date}\n\n";
        $message .= "نتمنى لكم التوفيق.\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendDeductionNotification($employee, $amount, $reason)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "💸 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "تم خصم من راتبك:\n";
        $message .= "المبلغ: " . number_format($amount) . " جنيه سوداني\n";
        $message .= "السبب: {$reason}\n\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendAppointmentNotification($employee, $position, $startDate)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "🎊 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "يسرنا إخطاركم بقبول طلب التعيين:\n";
        $message .= "المسمى الوظيفي: {$position}\n";
        $message .= "تاريخ مباشرة العمل: {$startDate}\n\n";
        $message .= "نرحب بكم في فريقنا ونتطلع للعمل معكم.\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendTerminationNotification($employee, $reason, $date)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $message = "📋 {$orgName}\n\n";
        $message .= "عزيزي/ {$employee->name}\n\n";
        $message .= "نُفيدكم بأنه تم إنهاء خدماتكم:\n";
        $message .= "السبب: {$reason}\n";
        $message .= "تاريخ الإنهاء: {$date}\n\n";
        $message .= "يرجى التوجه لقسم الموارد البشرية لتسوية مستحقاتكم.\n";
        $message .= "نشكركم على فترة عملكم معنا ونتمنى لكم التوفيق.\n";
        $message .= "قسم الموارد البشرية";
        
        return $this->sendToEmployee($employee->id, $message);
    }

    public function sendAdminNotification($message)
    {
        if (!$this->isEnabled() || empty($this->notifyPhone)) {
            return false;
        }
        return $this->sendMessage($this->notifyPhone, $message);
    }

    public function notifyAdminAppointment($employee, $position, $startDate)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "✅ تم تعيين موظف جديد\n";
        $msg .= "الاسم: {$employee->name}\n";
        $msg .= "المسمى: {$position}\n";
        $msg .= "تاريخ المباشرة: {$startDate}\n";
        
        if (!empty($employee->phone)) {
            $msg .= "الهاتف: {$employee->phone}\n";
        }
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminTermination($employee, $reason, $date)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "❌ تم فصل موظف\n";
        $msg .= "الاسم: {$employee->name}\n";
        $msg .= "السبب: {$reason}\n";
        $msg .= "التاريخ: {$date}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminWarning($employee, $warning)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "⚠️ تم إصدار إنذار\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "النوع: {$warning->type}\n";
        $msg .= "السبب: {$warning->reason}\n";
        $msg .= "التاريخ: {$warning->date}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminLeave($employee, $leave, $status)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $statusText = match($status) {
            'approved' => 'تمت الموافقة ✅',
            'rejected' => 'تم الرفض ❌',
            'pending' => 'قيد المراجعة ⏳',
            default => $status,
        };
        
        $leaveType = match($leave->type) {
            'official' => 'رسمية',
            'sick' => 'مرضية',
            'maternity' => 'أمومة',
            'hajj' => 'حج',
            'unpaid' => 'بدون مرتب',
            default => $leave->type,
        };
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "🏖️ طلب إجازة\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "النوع: {$leaveType}\n";
        $msg .= "من: {$leave->from_date}\n";
        $msg .= "إلى: {$leave->to_date}\n";
        $msg .= "الحالة: {$statusText}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminAdvance($employee, $advance, $status)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $statusText = match($status) {
            'approved' => 'تمت الموافقة ✅',
            'rejected' => 'تم الرفض ❌',
            default => $status,
        };
        
        $advanceType = $advance->type === 'short' ? 'قصيرة' : 'طويلة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "💰 طلب سلفة {$advanceType}\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "المبلغ: " . number_format($advance->amount) . " جنيه\n";
        $msg .= "الحالة: {$statusText}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminLate($employee, $date, $lateMinutes)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "⏰ تأخر في الحضور\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "التاريخ: {$date}\n";
        $msg .= "مدة التأخير: {$lateMinutes} دقيقة\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminAbsence($employee, $date)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "🚫 غياب\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "التاريخ: {$date}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminIncentive($employee, $amount, $reason)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "🎁 حافز\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "المبلغ: " . number_format($amount) . " جنيه\n";
        $msg .= "السبب: {$reason}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminDeduction($employee, $amount, $reason)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "💸 خصم\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "المبلغ: " . number_format($amount) . " جنيه\n";
        $msg .= "السبب: {$reason}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminResignation($employee, $status)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $statusText = match($status) {
            'approved' => 'تمت الموافقة ✅',
            'rejected' => 'تم الرفض ❌',
            'pending' => 'قيد المراجعة ⏳',
            default => $status,
        };
        
        $msg = "🏢 *{$orgName}* - إشعار إداري\n\n";
        $msg .= "📝 طلب استقالة\n";
        $msg .= "الموظف: {$employee->name}\n";
        $msg .= "الحالة: {$statusText}\n";
        
        return $this->sendAdminNotification($msg);
    }

    public function notifyAdminDailySummary($lateEmployees, $absentEmployees, $date)
    {
        $org = Setting::where('key', 'organization')->first();
        $orgName = $org ? ($org->value['name'] ?? 'المؤسسة') : 'المؤسسة';
        
        $msg = "🏢 *{$orgName}* - ملخص يومي\n";
        $msg .= "📅 {$date}\n\n";
        
        if (count($lateEmployees) > 0) {
            $msg .= "⏰ المتأخرون (" . count($lateEmployees) . "):\n";
            foreach ($lateEmployees as $emp) {
                $msg .= "- {$emp['name']}: {$emp['minutes']} دقيقة\n";
            }
            $msg .= "\n";
        }
        
        if (count($absentEmployees) > 0) {
            $msg .= "🚫 الغياب (" . count($absentEmployees) . "):\n";
            foreach ($absentEmployees as $name) {
                $msg .= "- {$name}\n";
            }
            $msg .= "\n";
        }
        
        if (count($lateEmployees) === 0 && count($absentEmployees) === 0) {
            $msg .= "✅ لا يوجد متأخرين أو غياب اليوم\n";
        }
        
        return $this->sendAdminNotification($msg);
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '249' . substr($phone, 1);
        }
        
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}
