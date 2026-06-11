<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\LetterLog;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LettersController extends Controller
{
    public function exportPdf(Request $request)
    {
        require base_path('vendor/tecnickcom/tcpdf/tcpdf.php');
        
        $type = $request->input('type');
        $employeeId = $request->input('employee_id');
        $params = $request->all();
        
        $employee = Employee::with(['department'])->findOrFail($employeeId);
        
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];
        
        $letterData = [
            'organization' => [
                'name' => $orgData['name'] ?? 'مؤسسة Jawda HR',
                'address' => $orgData['address'] ?? '',
                'phone' => $orgData['phone'] ?? '',
                'email' => $orgData['email'] ?? '',
                'logo_url' => isset($orgData['logo']) ? public_path('storage/' . $orgData['logo']) : null,
                'stamp_url' => isset($orgData['stamp']) ? public_path('storage/' . $orgData['stamp']) : null,
                'logo_web_url' => isset($orgData['logo']) ? asset('storage/' . $orgData['logo']) : null,
                'stamp_web_url' => isset($orgData['stamp']) ? asset('storage/' . $orgData['stamp']) : null,
            ],
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_number ?? $employee->file_number ?? $employee->id,
                'department' => $employee->department?->name ?? '-',
                'job_title' => $employee->position ?? '-',
                'job_type' => $employee->job_type ?? '-',
                'hire_date' => $employee->hire_date,
                'national_id' => $employee->national_id ?? '-',
                'base_salary' => $employee->base_salary ?? 0,
                'phone' => $employee->phone ?? '-',
                'email' => $employee->email ?? '-',
            ],
            'date' => Carbon::now()->format('Y-m-d'),
            'date_formatted' => Carbon::now()->locale('ar')->translatedFormat('d F Y'),
            'hijri_date' => $this->getHijriDate(),
        ];
        
        $content = match($type) {
            'termination' => $this->generateTerminationLetter($letterData, $params),
            'warning' => $this->generateWarningLetter($letterData, $params),
            'good_conduct' => $this->generateGoodConductCertificate($letterData, $params),
            'salary_verification' => $this->generateSalaryVerification($letterData, $params),
            'experience' => $this->generateExperienceCertificate($letterData, $params),
            'salary_increase' => $this->generateSalaryIncreaseLetter($letterData, $params),
            'leave_approval' => $this->generateLeaveApproval($letterData, $params),
            'loan_approval' => $this->generateLoanApproval($letterData, $params),
            'appointment' => $this->generateAppointmentLetter($letterData, $params),
            'transfer' => $this->generateTransferLetter($letterData, $params),
            'commendation' => $this->generateCommendationLetter($letterData, $params),
            default => null,
        };
        
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', true);
        $pdf->SetCreator('Jawda HR');
        $pdf->SetAuthor($letterData['organization']['name']);
        $pdf->SetTitle($content['title'] ?? 'خطاب');
        $pdf->SetSubject($content['subject'] ?? 'خطاب');
        
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 12);
        $pdf->SetAutoPageBreak(true, 20);
        
        $pdf->AddPage();
        
        $html = $this->generatePdfLetterHtml($letterData, $content, $orgData);
        $pdf->writeHTML($html, true, false, true, false, 'R');
        
        $typeLabel = $this->getLetterTypeLabel($type);
        $filename = "خطاب_{$typeLabel}_{$employee->name}_" . date('Ymd') . ".pdf";
        
        $pdfContent = $pdf->Output($filename, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*');
    }
    
    private function generatePdfLetterHtml($data, $content, $org)
    {
        $logoHtml = '';
        if (!empty($data['organization']['logo_url']) && file_exists($data['organization']['logo_url'])) {
            $logoBase64 = base64_encode(file_get_contents($data['organization']['logo_url']));
            $logoExt = pathinfo($data['organization']['logo_url'], PATHINFO_EXTENSION);
            $logoMime = 'image/' . ($logoExt === 'jpg' ? 'jpeg' : $logoExt);
            $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoBase64 . '" style="height:55px;width:55px;object-fit:contain;">';
        } elseif (!empty($data['organization']['logo_web_url'])) {
            $logoHtml = '<img src="' . $data['organization']['logo_web_url'] . '" style="height:55px;width:55px;object-fit:contain;">';
        }
        
        $stampHtml = '';
        if (!empty($data['organization']['stamp_url']) && file_exists($data['organization']['stamp_url'])) {
            $stampBase64 = base64_encode(file_get_contents($data['organization']['stamp_url']));
            $stampExt = pathinfo($data['organization']['stamp_url'], PATHINFO_EXTENSION);
            $stampMime = 'image/' . ($stampExt === 'jpg' ? 'jpeg' : $stampExt);
            $stampHtml = '<img src="data:' . $stampMime . ';base64,' . $stampBase64 . '" style="height:55px;width:55px;object-fit:contain;opacity:0.85;">';
        } elseif (!empty($data['organization']['stamp_web_url'])) {
            $stampHtml = '<img src="' . $data['organization']['stamp_web_url'] . '" style="height:55px;width:55px;object-fit:contain;opacity:0.85;">';
        }
        
        $body = $content['body'] ?? '';
        $body = str_replace('شركة', 'مؤسسة', $body);
        $body = str_replace('مدير عام الشركة', 'مدير عام المؤسسة', $body);
        
        // Add stamps to signature sections
        if (!empty($stampHtml)) {
            $body = str_replace(
                "<div class='signature-stamp' data-stamp='hr'></div>",
                '<div style="margin-top:10px;text-align:center;">' . $stampHtml . '</div>',
                $body
            );
            $body = str_replace(
                "<div class='signature-stamp' data-stamp='manager'></div>",
                '<div style="margin-top:10px;text-align:center;">' . $stampHtml . '</div>',
                $body
            );
        } else {
            $body = str_replace("<div class='signature-stamp' data-stamp='hr'></div>", '<div style="height:65px;display:flex;align-items:center;justify-content:center;"><div style="width:55px;height:55px;border:1.5px dashed #cbd5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div></div>', $body);
            $body = str_replace("<div class='signature-stamp' data-stamp='manager'></div>", '<div style="height:65px;display:flex;align-items:center;justify-content:center;"><div style="width:55px;height:55px;border:1.5px dashed #cbd5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div></div>', $body);
        }
        
        $logoPlaceholder = '<div style="width:55px;height:55px;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:9px;font-weight:bold;">شعار</div>';
        
        $html = '
        <style>
            body { 
                font-family: dejavusans, sans-serif; 
                font-size: 12px; 
                line-height: 2; 
                text-align: right;
                margin: 0;
                padding: 20px;
                color: #1e293b;
            }
            .org-header-table { 
                width: 100%;
                border: none;
                border-collapse: collapse;
                margin-bottom: 12px;
            }
            .org-header-table td {
                border: none;
                vertical-align: middle;
                padding: 5px;
            }
            .header-gradient {
                height: 3px;
                background: linear-gradient(90deg, #1e3a5f, #3b82f6, #6366f1, #3b82f6, #1e3a5f);
                margin: 8px 0 15px 0;
                border-radius: 2px;
            }
            .org-name { 
                font-size: 18px; 
                font-weight: bold; 
                color: #1e3a5f;
                margin: 0;
                letter-spacing: 0.5px;
            }
            .org-subtitle {
                font-size: 9px;
                color: #64748b;
                margin: 3px 0;
            }
            .letter-title {
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                border: 3px solid #1e3a5f;
                background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                border-radius: 10px;
            }
            .letter-title h2 {
                font-size: 18px;
                margin: 0;
                color: #1e3a5f;
            }
            .recipient-info { 
                margin: 15px 0; 
                padding: 12px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
            }
            .subject-box { 
                margin: 15px 0; 
                padding: 10px 15px; 
                background: #fef3c7;
                border-right: 4px solid #d97706;
                border-radius: 4px;
            }
            .subject-box strong { color: #92400e; }
            .content-body { 
                text-align: justify; 
                margin: 20px 0; 
                line-height: 2;
            }
            .signature-section { 
                margin-top: 40px; 
            }
            .signature-box { 
                text-align: center; 
                width: 48%; 
                display: inline-block;
                padding: 10px;
            }
            .signature-left { float: right; }
            .signature-right { float: left; }
            .signature-title {
                font-weight: bold;
                font-size: 12px;
                margin-bottom: 8px;
                color: #1e3a5f;
            }
            .signature-line {
                border-bottom: 1.5px solid #1e3a5f;
                width: 120px;
                margin: 0 auto 5px auto;
            }
            .stamp-area {
                margin-top: 8px;
                min-height: 65px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .border-box { 
                border: 2px solid #cbd5e1; 
                padding: 15px; 
                margin: 15px 0; 
                border-radius: 8px;
            }
            .clear { clear: both; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-lg { font-size: 13px; }
            .text-xl { font-size: 15px; }
            .text-2xl { font-size: 18px; }
            .text-3xl { font-size: 22px; }
            .font-bold { font-weight: bold; }
            .mb-4 { margin-bottom: 10px; }
            .mb-6 { margin-bottom: 15px; }
            .mb-8 { margin-bottom: 20px; }
            .mt-2 { margin-top: 5px; }
            .mt-8 { margin-top: 20px; }
            .p-6 { padding: 15px; }
            .my-4 { margin-top: 10px; margin-bottom: 10px; }
            .mr-8 { margin-right: 20px; }
            .border-2 { border-width: 2px; }
            .border-black { border-color: #1e293b; }
            .w-full { width: 100%; }
            .bg-gray-50 { background: #f8fafc; }
            ul, ol { margin: 10px 0; }
            li { margin: 5px 0; line-height: 1.8; }
            p { margin: 8px 0; }
            .salutation { margin: 10px 0; }
            .content { margin: 15px 0; line-height: 2; }
            .closing { margin: 15px 0; }
            .signature { margin-top: 30px; }
            table.w-full { border-collapse: collapse; width: 100%; }
            table.w-full td { padding: 8px; }
            .border { border: 1px solid #e2e8f0; }
            .p-2 { padding: 8px; }
            .p-3 { padding: 10px; }
            .p-4 { padding: 12px; }
            .flex { display: flex; }
            .justify-between { justify-content: space-between; }
            .footer-divider {
                height: 1.5px;
                background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
                margin: 20px 0;
            }
            .footer-info {
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 6px 12px;
                margin-top: 12px;
                text-align: center;
            }
            .letter-header p { margin: 3px 0; }
            hr { border: none; border-top: 1px solid #e2e8f0; }
        </style>
        
        <table class="org-header-table">
            <tr>
                <td style="width:65px;text-align:center;">
                    ' . ($logoHtml ?: $logoPlaceholder) . '
                </td>
                <td style="text-align:center;padding:5px 10px;">
                    <h1 class="org-name">' . htmlspecialchars($data['organization']['name']) . '</h1>
                    <p class="org-subtitle">
                        ' . ($data['organization']['address'] ? 'العنوان: ' . htmlspecialchars($data['organization']['address']) : '') . '
                        ' . ($data['organization']['phone'] ? ' | هاتف: ' . htmlspecialchars($data['organization']['phone']) : '') . '
                    </p>
                    ' . ($data['organization']['email'] ? '<p class="org-subtitle">بريد: ' . htmlspecialchars($data['organization']['email']) . '</p>' : '') . '
                </td>
                <td style="width:65px;"></td>
            </tr>
        </table>
        <div class="header-gradient"></div>
        
        <div class="letter-title">
            <h2>' . htmlspecialchars($content['title'] ?? 'خطاب') . '</h2>
        </div>
        
        ' . $body . '
        
        <div class="footer-divider"></div>
        
        <table style="width:100%;border:none;margin-top:20px;border-collapse:collapse;">
            <tr>
                <td style="width:30%;text-align:center;border:none;vertical-align:top;padding:5px;">
                    <div class="stamp-area">' . ($stampHtml ?: '<div style="width:55px;height:55px;border:1.5px dashed #cbd5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:7px;font-weight:bold;">ختم</div>') . '</div>
                    <p style="font-size:8px;color:#64748b;margin:4px 0 0 0;">ختم المؤسسة</p>
                </td>
                <td style="width:35%;text-align:center;border:none;vertical-align:top;padding:5px;">
                    <div class="signature-line" style="border-bottom:1.5px solid #1e3a5f;width:120px;"></div>
                    <p class="signature-title" style="font-size:10px;">مدير الموارد البشرية</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التاريخ: ....................</p>
                </td>
                <td style="width:35%;text-align:center;border:none;vertical-align:top;padding:5px;">
                    <div class="signature-line" style="border-bottom:1.5px solid #059669;width:120px;"></div>
                    <p class="signature-title" style="font-size:10px;color:#059669;">المدير العام</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">الاسم: ........................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التوقيع: ....................</p>
                    <p style="font-size:7px;color:#94a3b8;margin:2px 0;">التاريخ: ....................</p>
                </td>
            </tr>
        </table>
        
        <div class="footer-info">
            <p style="font-size:7px;color:#64748b;margin:0;">
                <strong>Jawda HR</strong> — نظام إدارة الموارد البشرية | تاريخ الطباعة: ' . now()->format('Y-m-d H:i') . '
            </p>
        </div>
        
        <div class="clear"></div>
        ';
        
        return $html;
    }

    public function generate(Request $request)
    {
        $type = $request->input('type');
        $employeeId = $request->input('employee_id');
        
        $employee = Employee::with(['department'])
            ->findOrFail($employeeId);
        
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];
        
        $letterData = [
            'organization' => [
                'name' => $orgData['name'] ?? 'مؤسسة Jawda HR',
                'address' => $orgData['address'] ?? '',
                'phone' => $orgData['phone'] ?? '',
                'email' => $orgData['email'] ?? '',
                'logo_url' => isset($orgData['logo']) ? asset('storage/' . $orgData['logo']) : null,
                'stamp_url' => isset($orgData['stamp']) ? asset('storage/' . $orgData['stamp']) : null,
            ],
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_number ?? $employee->file_number ?? $employee->id,
                'department' => $employee->department?->name ?? '-',
                'job_title' => $employee->position ?? '-',
                'job_type' => $employee->job_type ?? '-',
                'hire_date' => $employee->hire_date,
                'national_id' => $employee->national_id ?? '-',
                'base_salary' => $employee->base_salary ?? 0,
                'phone' => $employee->phone ?? '-',
                'email' => $employee->email ?? '-',
            ],
            'date' => Carbon::now()->format('Y-m-d'),
            'date_formatted' => Carbon::now()->locale('ar')->translatedFormat('d F Y'),
            'Hijri_date' => $this->getHijriDate(),
        ];
        
        $content = match($type) {
            'termination' => $this->generateTerminationLetter($letterData, $request->all()),
            'warning' => $this->generateWarningLetter($letterData, $request->all()),
            'good_conduct' => $this->generateGoodConductCertificate($letterData, $request->all()),
            'salary_verification' => $this->generateSalaryVerification($letterData, $request->all()),
            'experience' => $this->generateExperienceCertificate($letterData, $request->all()),
            'salary_increase' => $this->generateSalaryIncreaseLetter($letterData, $request->all()),
            'leave_approval' => $this->generateLeaveApproval($letterData, $request->all()),
            'loan_approval' => $this->generateLoanApproval($letterData, $request->all()),
            'appointment' => $this->generateAppointmentLetter($letterData, $request->all()),
            'transfer' => $this->generateTransferLetter($letterData, $request->all()),
            'commendation' => $this->generateCommendationLetter($letterData, $request->all()),
            default => null,
        };

        $letterLog = LetterLog::logLetter(
            $type,
            $content['title'] ?? 'خطاب',
            $employeeId,
            $request->all(),
            $content['body'] ?? ''
        );

        $whatsapp = new WhatsAppService();

        if (in_array($type, ['appointment', 'termination'])) {
            if ($type === 'appointment') {
                $whatsapp->sendAppointmentNotification(
                    $employee,
                    $request->input('position', $employee->position ?? '-'),
                    $request->input('start_date', now()->format('Y-m-d'))
                );
            } elseif ($type === 'termination') {
                $whatsapp->sendTerminationNotification(
                    $employee,
                    $request->input('reason', 'انهاء الخدمة'),
                    $request->input('termination_date', now()->format('Y-m-d'))
                );
            }
        }

        Notification::send(
            auth()->id(),
            'letter',
            'خطاب جديد',
            "تم إنشاء خطاب {$this->getLetterTypeLabel($type)} لـ {$employee->name}",
            ['letter_id' => $letterLog->id, 'employee_id' => $employeeId]
        );
        
        // Replace company references in the body
        if (isset($content['body'])) {
            $content['body'] = str_replace('شركة', 'مؤسسة', $content['body']);
            $content['body'] = str_replace('مدير عام الشركة', 'مدير عام المؤسسة', $content['body']);
        }
        
        return response()->json([
            'type' => $type,
            'type_label' => $this->getLetterTypeLabel($type),
            'reference_number' => $letterLog->reference_number,
            'data' => $letterData,
            'content' => $content,
            'logo_url' => $letterData['organization']['logo_url'],
            'stamp_url' => $letterData['organization']['stamp_url'],
        ]);
    }

    private function getHijriDate()
    {
        if (extension_loaded('intl')) {
            try {
                $formatter = new \IntlDateFormatter('ar_SA', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'Asia/Riyadh', \IntlDateFormatter::GREGORIAN);
                return $formatter->format(now());
            } catch (\Exception $e) {
                return now()->format('Y/m/d');
            }
        }
        return now()->format('Y/m/d');
    }

    private function generateTerminationLetter($data, $params)
    {
        $reason = $params['reason'] ?? 'انهاء الخدمة';
        $terminationDate = $params['termination_date'] ?? Carbon::now()->format('Y-m-d');
        $noticeDate = $params['notice_date'] ?? Carbon::now()->subDays(30)->format('Y-m-d');
        
        $years = Carbon::parse($data['employee']['hire_date'])->diffInYears(Carbon::parse($terminationDate));
        $months = Carbon::parse($data['employee']['hire_date'])->diffInMonths(Carbon::parse($terminationDate)) % 12;
        
        return [
            'title' => 'خطاب إنهاء خدمة',
            'subject' => "إنهاء عقد العمل رقم ({$data['employee']['employee_code']})",
            'body' => "
                <div class='letter-header'>
                    <p class='text-center text-lg mb-4'>{$data['date_formatted']}</p>
                    <p class='text-center text-lg mb-4'>{$this->getHijriDate()}</p>
                </div>
                <div class='recipient-info mb-6'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                </div>
                <div class='subject mb-6'>
                    <p class='font-bold text-xl'>الموضوع: إنهاء عقد العمل</p>
                </div>
                <div class='salutation mb-4'>
                    <p>تحية طيبة وبعد،</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4'>نُفيدكم بأن إدارة الموارد البشرية قد تابعت إجراءات إنهاء خدماتكم لدى مؤسسة 
                        <strong>{$data['organization']['name']}</strong>، 
                        وذلك استناداً إلى <strong>{$reason}</strong>.</p>
                    <p class='mb-4'>تشير سجلاتنا إلى أن تاريخ تعيينكم كان <strong>{$data['employee']['hire_date']}</strong>، 
                        وقد أمضيتم معنا <strong>{$years} سنة و {$months} شهر</strong>.</p>
                    <p class='mb-4'>سيتم إنهاء خدماتكم اعتباراً من تاريخ: <strong>{$terminationDate}</strong></p>
                    <p>تاريخ إرسال هذا الخطاب: <strong>{$noticeDate}</strong></p>
                </div>
                <div class='closing mb-6'>
                    <p class='mb-4'>يرجى التوجه إلى قسم الموارد البشرية لتسوية مستحقاتكم.</p>
                    <p class='mb-4'>نتقدم إليكم بخالص الشكر والتقدير على فترة عملكم معنا، 
                        ونتمنى لكم التوفيق في مسيرتكم المهنية.</p>
                </div>
                <div class='signature'>
                    <p>مع خالص التحية،</p>
                    <br/>
                    <p class='font-bold'>إدارة الموارد البشرية</p>
                    <p>{$data['organization']['name']}</p>
                    <p>{$data['organization']['address']}</p>
                    <p>ت: {$data['organization']['phone']}</p>
                </div>
            ",
        ];
    }

    private function generateWarningLetter($data, $params)
    {
        $warningReason = $params['warning_reason'] ?? 'المخالفة المرتكبة';
        $warningType = $params['warning_type'] ?? 'إنذار';
        $warningDate = $params['warning_date'] ?? Carbon::now()->format('Y-m-d');
        
        return [
            'title' => 'خطاب ' . $warningType,
            'subject' => "{$warningType} موظف رقم ({$data['employee']['employee_code']})",
            'body' => "
                <div class='letter-header'>
                    <p class='text-center text-lg mb-2'>التاريخ: " . Carbon::parse($warningDate)->locale('ar')->translatedFormat('d F Y') . "</p>
                    <p class='text-center text-lg mb-4'>الرقم المرجعي: {$data['organization']['name']}-WRN-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                </div>
                <div class='recipient-info mb-6'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                </div>
                <div class='subject mb-6'>
                    <p class='font-bold text-xl'>الموضوع: {$warningType}</p>
                </div>
                <div class='salutation mb-4'>
                    <p>تحية طيبة وبعد،</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4'>نُفيدكم بأنه قد تم رصد <strong>{$warningReason}</strong>، وذلك في تاريخ " . Carbon::parse($warningDate)->locale('ar')->translatedFormat('d F Y') . ".</p>
                    <p class='mb-4'>نأمل منكم:</p>
                    <ul class='list-disc mr-8 my-4'>
                        <li>التوقف الفوري عن السلوك المخالف</li>
                        <li>الالتزام بالأنظمة والسياسات المعتمدة</li>
                        <li>تحسين أداءكم الوظيفي</li>
                    </ul>
                    <p class='mb-4'>وفي حالة تكرار المخالفة، سيتم اتخاذ الإجراءات التأديبية اللازمة وفقاً للائحة الداخلية للمؤسسة.</p>
                </div>
                <div class='signature'>
                    <p>التوقيع بالعلم والخبرة.</p>
                    <br/>
                    <br/>
                    <p>الاسم: ________________________</p>
                    <p>التوقيع: ________________________</p>
                    <p>التاريخ: ________________________</p>
                </div>
            ",
        ];
    }

    private function generateGoodConductCertificate($data, $params)
    {
        $purpose = $params['purpose'] ?? 'غرض_T';
        
        return [
            'title' => 'شهادة حسن سير وسلوك',
            'subject' => "شهادة حسن سير وسلوك - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-2'>شهادة حسن سير وسلوك</p>
                    <p class='text-lg'>( Certificate of Good Conduct )</p>
                    <p class='mt-2'>رقم الشهادة: {$data['organization']['name']}-GC-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='border-2 border-black p-6 my-4'>
                    <p class='text-center font-bold text-xl mb-4'>تشهد مؤسسة</p>
                    <p class='text-center text-2xl font-bold mb-4'>{$data['organization']['name']}</p>
                    <p class='mb-4'> بأن السيد/ <strong>{$data['employee']['name']}</strong></p>
                    <p>بالرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>الرقم القومي: {$data['employee']['national_id']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                    <br/>
                    <p>مُنِحَ هذه الشهادة وهو يعمل لديها منذ تاريخ: <strong>{$data['employee']['hire_date']}</strong></p>
                    <br/>
                    <p>وبناءً على متابعة سلوكه أثناء فترة عمله، فإننا نشهد بأن:</p>
                    <ul class='list-disc mr-8 my-4'>
                        <li>سيرته الذاتية حسنة</li>
                        <li>لم يسبق له ارتكاب أي مخالفة</li>
                        <li>أداؤه الوظيفي مُرضٍ</li>
                        <li>يتصف بالأمانة والالتزام</li>
                    </ul>
                    <br/>
                    <p>هذه الشهادة تُصدر لـ: <strong>{$purpose}</strong></p>
                </div>
                <br/>
                <div class='signature-section'>
                    <div class='signature-box signature-left'>
                        <p>مدير الموارد البشرية</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='hr'></div>
                    </div>
                    <div class='signature-box signature-right'>
                        <p>مدير عام المؤسسة</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='manager'></div>
                    </div>
                </div>
            ",
        ];
    }

    private function generateSalaryVerification($data, $params)
    {
        $purpose = $params['purpose'] ?? 'غرض_T';
        $salary = $data['employee']['base_salary'];
        
        return [
            'title' => 'شهادة إثبات مرتب',
            'subject' => "شهادة إثبات مرتب - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-2'>شهادة إثبات مرتب</p>
                    <p class='text-lg'>( Salary Verification Certificate )</p>
                    <p class='mt-2'>رقم الشهادة: {$data['organization']['name']}-SV-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='border-2 border-black p-6 my-4'>
                    <p class='text-center font-bold text-xl mb-4'>تُشهد مؤسسة</p>
                    <p class='text-center text-2xl font-bold mb-4'>{$data['organization']['name']}</p>
                    <p class='mb-4'>بأن السيد/ <strong>{$data['employee']['name']}</strong></p>
                    <p>بالرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                    <p>نوع التعاقد: {$data['employee']['job_type']}</p>
                    <br/>
                    <p>يتقاضى راتباً شهرياً قدره:</p>
                    <p class='text-center text-3xl font-bold my-4'>" . number_format($salary, 2) . " جنيه سوداني</p>
                    <p class='text-center'>(فقط {$this->numberToArabic($salary)} جنيه سوداني لا غير)</p>
                    <br/>
                    <p>تاريخ التعيين: {$data['employee']['hire_date']}</p>
                    <br/>
                    <p>هذه الشهادة تُصدر لـ: <strong>{$purpose}</strong></p>
                    <p>وبرغبة من صاحب الشأن لإرفاقها بالملف.</p>
                </div>
                <br/>
                <div class='signature-section'>
                    <div class='signature-box signature-left'>
                        <p>مدير الموارد البشرية</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='hr'></div>
                    </div>
                    <div class='signature-box signature-right'>
                        <p>مدير عام المؤسسة</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='manager'></div>
                    </div>
                </div>
            ",
        ];
    }

    private function generateExperienceCertificate($data, $params)
    {
        $purpose = $params['purpose'] ?? 'غرض_T';
        $years = Carbon::parse($data['employee']['hire_date'])->diffInYears(now());
        $months = Carbon::parse($data['employee']['hire_date'])->diffInMonths(now()) % 12;
        
        return [
            'title' => 'شهادة خبرة',
            'subject' => "شهادة خبرة - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-2'>شهادة خبرة</p>
                    <p class='text-lg'>( Certificate of Experience )</p>
                    <p class='mt-2'>رقم الشهادة: {$data['organization']['name']}-EX-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='border-2 border-black p-6 my-4'>
                    <p class='text-center font-bold text-xl mb-4'>تُشهد مؤسسة</p>
                    <p class='text-center text-2xl font-bold mb-2'>{$data['organization']['name']}</p>
                    <p class='text-center mb-4'>{$data['organization']['address']}</p>
                    <br/>
                    <p class='mb-4'>بأن السيد/ <strong>{$data['employee']['name']}</strong></p>
                    <p>بالرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>الرقم القومي: {$data['employee']['national_id']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                    <p>نوع التعاقد: {$data['employee']['job_type']}</p>
                    <br/>
                    <p>قد عمل لديها خلال الفترة من: <strong>{$data['employee']['hire_date']}</strong> 
                        وحتى: <strong>" . now()->format('Y-m-d') . "</strong></p>
                    <p>بإجمالي مدة خدمة: <strong>{$years} سنة و {$months} شهر</strong></p>
                    <br/>
                    <p>وخلال فترة عمله، تميز بـ:</p>
                    <ul class='list-disc mr-8 my-4'>
                        <li>الالتزام التام بمواعيد العمل</li>
                        <li>أداء مهام عمله بكفاءة واقتدار</li>
                        <li>العمل بروح الفريق</li>
                        <li>الاحترافية في التعامل</li>
                    </ul>
                    <br/>
                    <p>هذه الشهادة تُصدر لـ: <strong>{$purpose}</strong></p>
                </div>
                <br/>
                <div class='signature-section'>
                    <div class='signature-box signature-left'>
                        <p>مدير الموارد البشرية</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='hr'></div>
                    </div>
                    <div class='signature-box signature-right'>
                        <p>مدير عام المؤسسة</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <div class='stamp-area signature-stamp' data-stamp='manager'></div>
                    </div>
                </div>
            ",
        ];
    }

    private function generateSalaryIncreaseLetter($data, $params)
    {
        $oldSalary = $params['old_salary'] ?? 0;
        $newSalary = $params['new_salary'] ?? 0;
        $increasePercent = $oldSalary > 0 ? round((($newSalary - $oldSalary) / $oldSalary) * 100, 2) : 0;
        $effectiveDate = $params['effective_date'] ?? now()->format('Y-m-d');
        $reason = $params['reason'] ?? 'الزيادة السنوية';
        
        return [
            'title' => 'خطاب زيادة المرتب',
            'subject' => "خطاب زيادة المرتب - {$data['employee']['name']}",
            'body' => "
                <div class='letter-header'>
                    <p class='text-center text-lg mb-2'>التاريخ: {$data['date_formatted']}</p>
                    <p class='text-center text-lg mb-4'>الرقم المرجعي: {$data['organization']['name']}-SI-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                </div>
                <div class='recipient-info mb-6'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>القسم: {$data['employee']['department']}</p>
                    <p>المسمّى الوظيفي: {$data['employee']['job_title']}</p>
                </div>
                <div class='subject mb-6'>
                    <p class='font-bold text-xl'>الموضوع: إخطار بزيادة المرتب</p>
                </div>
                <div class='salutation mb-4'>
                    <p>تحية طيبة وبعد،</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4'>نُفيدكم بأنه قد تقرر تعديل راتبكم الشهري اعتباراً من {$effectiveDate}، 
                        وذلك وفقاً للآتي:</p>
                    <br/>
                    <table class='w-full border-collapse my-4'>
                        <tr>
                            <td class='border p-3'>المرتب الحالي</td>
                            <td class='border p-3 text-left'>" . number_format($oldSalary, 2) . " جنيه سوداني</td>
                        </tr>
                        <tr>
                            <td class='border p-3'>المرتب الجديد</td>
                            <td class='border p-3 text-left'>" . number_format($newSalary, 2) . " جنيه سوداني</td>
                        </tr>
                        <tr>
                            <td class='border p-3'>نسبة الزيادة</td>
                            <td class='border p-3 text-left'>{$increasePercent}%</td>
                        </tr>
                        <tr>
                            <td class='border p-3'>قيمة الزيادة</td>
                            <td class='border p-3 text-left'>" . number_format($newSalary - $oldSalary, 2) . " جنيه سوداني</td>
                        </tr>
                    </table>
                    <br/>
                    <p>السبب: <strong>{$reason}</strong></p>
                    <p class='mt-4'>نتقدم إليكم بالتهنئة، وندعوكم إلى الاستمرار في العطاء والإبداع.</p>
                </div>
                <div class='signature'>
                    <p>مع خالص التحية،</p>
                    <br/>
                    <p class='font-bold'>إدارة الموارد البشرية</p>
                    <p>{$data['organization']['name']}</p>
                </div>
            ",
        ];
    }

    private function generateLeaveApproval($data, $params)
    {
        $leaveType = $params['leave_type'] ?? 'إجازة';
        $startDate = $params['start_date'] ?? now()->format('Y-m-d');
        $endDate = $params['end_date'] ?? now()->addDays(3)->format('Y-m-d');
        $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $reason = $params['reason'] ?? '-';
        
        return [
            'title' => 'موافقة إجازة',
            'subject' => "موافقة {$leaveType} - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-2'>طلب موافقة إجازة</p>
                    <p class='text-lg'>( Leave Request Form )</p>
                    <p class='mt-2'>الرقم المرجعي: {$data['organization']['name']}-LV-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='border-2 border-black p-6 my-4'>
                    <table class='w-full'>
                        <tr>
                            <td class='p-2 font-bold'>الاسم:</td>
                            <td class='p-2'><strong>{$data['employee']['name']}</strong></td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>الرقم الوظيفي:</td>
                            <td class='p-2'>{$data['employee']['employee_code']}</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>القسم:</td>
                            <td class='p-2'>{$data['employee']['department']}</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>المسمّى الوظيفي:</td>
                            <td class='p-2'>{$data['employee']['job_title']}</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>نوع الإجازة:</td>
                            <td class='p-2'><strong>{$leaveType}</strong></td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>من تاريخ:</td>
                            <td class='p-2'><strong>{$startDate}</strong></td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>إلى تاريخ:</td>
                            <td class='p-2'><strong>{$endDate}</strong></td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>عدد الأيام:</td>
                            <td class='p-2'><strong>{$days}</strong> يوم</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>السبب:</td>
                            <td class='p-2'>{$reason}</td>
                        </tr>
                    </table>
                </div>
                <br/>
                <div class='flex justify-between mt-8'>
                    <div class='text-center'>
                        <p>الموظف</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <p>التاريخ: _______________</p>
                    </div>
                    <div class='text-center'>
                        <p>مدير القسم</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <p>التاريخ: _______________</p>
                    </div>
                    <div class='text-center'>
                        <p>مدير الموارد البشرية</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                        <p>التاريخ: _______________</p>
                    </div>
                </div>
            ",
        ];
    }

    private function generateLoanApproval($data, $params)
    {
        $loanAmount = $params['loan_amount'] ?? 0;
        $installments = $params['installments'] ?? 1;
        $monthlyPayment = $loanAmount / $installments;
        $startDate = $params['start_date'] ?? now()->format('Y-m-d');
        $reason = $params['reason'] ?? '-';
        
        return [
            'title' => 'موافقة سلفة',
            'subject' => "موافقة سلفة - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-2'>طلب موافقة سلفة</p>
                    <p class='text-lg'>( Loan Request Form )</p>
                    <p class='mt-2'>الرقم المرجعي: {$data['organization']['name']}-LN-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='border-2 border-black p-6 my-4'>
                    <table class='w-full'>
                        <tr>
                            <td class='p-2 font-bold'>الاسم:</td>
                            <td class='p-2'><strong>{$data['employee']['name']}</strong></td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>الرقم الوظيفي:</td>
                            <td class='p-2'>{$data['employee']['employee_code']}</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>القسم:</td>
                            <td class='p-2'>{$data['employee']['department']}</td>
                        </tr>
                        <tr>
                            <td class='p-2 font-bold'>الراتب الحالي:</td>
                            <td class='p-2'>" . number_format($data['employee']['base_salary'], 2) . " جنيه سوداني</td>
                        </tr>
                    </table>
                    <br/>
                    <div class='border p-4 my-2 bg-gray-50'>
                        <p class='font-bold mb-2'>تفاصيل السلفة:</p>
                        <table class='w-full'>
                            <tr>
                                <td class='p-2'>مبلغ السلفة المطلوبة:</td>
                                <td class='p-2'><strong>" . number_format($loanAmount, 2) . "</strong> جنيه سوداني</td>
                            </tr>
                            <tr>
                                <td class='p-2'>عدد الأقساط:</td>
                                <td class='p-2'><strong>{$installments}</strong> شهر</td>
                            </tr>
                            <tr>
                                <td class='p-2'>قيمة القسط الشهري:</td>
                                <td class='p-2'><strong>" . number_format($monthlyPayment, 2) . "</strong> جنيه سوداني</td>
                            </tr>
                            <tr>
                                <td class='p-2'>بداية الخصم من:</td>
                                <td class='p-2'><strong>{$startDate}</strong></td>
                            </tr>
                        </table>
                    </div>
                    <br/>
                    <p><span class='font-bold'>السبب:</span> {$reason}</p>
                </div>
                <br/>
                <div class='flex justify-between mt-8'>
                    <div class='text-center'>
                        <p>الموظف</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                    </div>
                    <div class='text-center'>
                        <p>مدير الموارد البشرية</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                    </div>
                    <div class='text-center'>
                        <p>مدير عام المؤسسة</p>
                        <br/>
                        <p>التوقيع: _______________</p>
                    </div>
                </div>
            ",
        ];
    }

    private function generateAppointmentLetter($data, $params)
    {
        $position = $params['position'] ?? $data['employee']['job_title'];
        $department = $params['department'] ?? $data['employee']['department'];
        $startDate = $params['start_date'] ?? now()->format('Y-m-d');
        $salary = $params['salary'] ?? $data['employee']['base_salary'];
        $contractType = $params['contract_type'] ?? $data['employee']['job_type'];
        
        return [
            'title' => 'خطاب تعيين',
            'subject' => "خطاب تعيين - {$data['employee']['name']}",
            'body' => "
                <div class='letter-header'>
                    <p class='text-center text-lg mb-2'>التاريخ: {$data['date_formatted']}</p>
                    <p class='text-center text-lg mb-4'>الرقم المرجعي: {$data['organization']['name']}-APT-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                </div>
                <div class='recipient-info mb-6'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم القومي: {$data['employee']['national_id']}</p>
                    <p>الهاتف: {$data['employee']['phone']}</p>
                </div>
                <div class='subject mb-6'>
                    <p class='font-bold text-xl'>الموضوع: خطاب تعيين</p>
                </div>
                <div class='salutation mb-4'>
                    <p>تحية طيبة وبعد،</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4'>يسرنا نحن مؤسسة <strong>{$data['organization']['name']}</strong> أن نُinformedك بقبول طلب التعيين المقدم من سيادتكم، وذلك على النحو التالي:</p>
                    <br/>
                    <table class='w-full border-collapse my-4'>
                        <tr>
                            <td class='border p-3 font-bold'>المسمى الوظيفي:</td>
                            <td class='border p-3'><strong>{$position}</strong></td>
                        </tr>
                        <tr>
                            <td class='border p-3 font-bold'>القسم:</td>
                            <td class='border p-3'>{$department}</td>
                        </tr>
                        <tr>
                            <td class='border p-3 font-bold'>نوع التعاقد:</td>
                            <td class='border p-3'>{$contractType}</td>
                        </tr>
                        <tr>
                            <td class='border p-3 font-bold'>تاريخ مباشرة العمل:</td>
                            <td class='border p-3'><strong>{$startDate}</strong></td>
                        </tr>
                        <tr>
                            <td class='border p-3 font-bold'>الراتب:</td>
                            <td class='border p-3'>" . number_format($salary, 2) . " جنيه سوداني</td>
                        </tr>
                    </table>
                    <br/>
                    <p class='mb-4'>نرجو من سيادتكم التوجه إلى قسم الموارد البشرية في التاريخ المحدد لاستكمال إجراءات التعيين.</p>
                    <p>نتمنى لكم مسيرة مهنية ناجحة.</p>
                </div>
                <div class='signature'>
                    <p>مع خالص التحية،</p>
                    <br/>
                    <p class='font-bold'>إدارة الموارد البشرية</p>
                    <p>{$data['organization']['name']}</p>
                </div>
            ",
        ];
    }

    private function generateTransferLetter($data, $params)
    {
        $fromDept = $params['from_department'] ?? $data['employee']['department'];
        $toDept = $params['to_department'] ?? '-';
        $reason = $params['reason'] ?? 'مصلحة العمل';
        $effectiveDate = $params['effective_date'] ?? now()->format('Y-m-d');
        
        return [
            'title' => 'خطاب نقل',
            'subject' => "خطاب نقل - {$data['employee']['name']}",
            'body' => "
                <div class='letter-header'>
                    <p class='text-center text-lg mb-2'>التاريخ: {$data['date_formatted']}</p>
                    <p class='text-center text-lg mb-4'>الرقم المرجعي: {$data['organization']['name']}-TRF-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                </div>
                <div class='recipient-info mb-6'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>القسم الحالي: {$fromDept}</p>
                </div>
                <div class='subject mb-6'>
                    <p class='font-bold text-xl'>الموضوع: إخطار بالنقل</p>
                </div>
                <div class='salutation mb-4'>
                    <p>تحية طيبة وبعد،</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4'>نُفيدكم بأنه قد تقرر نقل سيادتكم من قسم <strong>{$fromDept}</strong> إلى قسم <strong>{$toDept}</strong>، اعتباراً من تاريخ <strong>{$effectiveDate}</strong>.</p>
                    <p class='mb-4'>السبب: <strong>{$reason}</strong></p>
                    <p>نرجو التوجه إلى القسم الجديد لاستلام مهامكم.</p>
                </div>
                <div class='signature'>
                    <p>مع خالص التحية،</p>
                    <br/>
                    <p class='font-bold'>إدارة الموارد البشرية</p>
                    <p>{$data['organization']['name']}</p>
                </div>
            ",
        ];
    }

    private function generateCommendationLetter($data, $params)
    {
        $reason = $params['reason'] ?? 'أداء متميز';
        $details = $params['details'] ?? '-';
        
        return [
            'title' => 'خطاب إشادة',
            'subject' => "خطاب إشادة - {$data['employee']['name']}",
            'body' => "
                <div class='text-center mb-8'>
                    <p class='text-2xl font-bold mb-4'>خطاب إشادة وتقدير</p>
                    <p class='text-lg'>( Letter of Commendation )</p>
                    <p class='mt-2'>الرقم المرجعي: {$data['organization']['name']}-CMM-" . str_pad($data['employee']['id'], 4, '0', STR_PAD_LEFT) . "-" . date('Y') . "</p>
                    <p>التاريخ: {$data['date_formatted']}</p>
                </div>
                <div class='recipient-info mb-6 text-center'>
                    <p><strong>السيد/ {$data['employee']['name']}</strong></p>
                    <p>الرقم الوظيفي: {$data['employee']['employee_code']}</p>
                    <p>{$data['employee']['department']} - {$data['employee']['job_title']}</p>
                </div>
                <div class='content mb-6'>
                    <p class='mb-4 text-center font-bold text-xl'>تهانينا!</p>
                    <p class='mb-4'>يسعدنا أن نُعلمكم أننا قد شهدنا منك <strong>{$reason}</strong>.</p>
                    <p class='mb-4'>تفاصيل: {$details}</p>
                    <p class='mb-4'>إن مساهمتكم تُعد نموذجاً يُحتذى به، ونسأل الله أن يوفقكم دائماً.</p>
                    <p>نفتخر بوجودكم ضمن فريقنا.</p>
                </div>
                <br/>
                <div class='text-center mt-8'>
                    <p class='font-bold'>مع أطيب التمنيات،</p>
                    <br/>
                    <p class='font-bold text-xl'>{$data['organization']['name']}</p>
                </div>
            ",
        ];
    }
    
    private function getLetterTypeLabel($type)
    {
        return match($type) {
            'termination' => 'خطاب إنهاء خدمة',
            'warning' => 'خطاب إنذار',
            'good_conduct' => 'شهادة حسن سير وسلوك',
            'salary_verification' => 'شهادة إثبات مرتب',
            'experience' => 'شهادة خبرة',
            'salary_increase' => 'خطاب زيادة المرتب',
            'leave_approval' => 'موافقة إجازة',
            'loan_approval' => 'موافقة سلفة',
            'appointment' => 'خطاب تعيين',
            'transfer' => 'خطاب نقل',
            'commendation' => 'خطاب إشادة',
            default => 'خطاب',
        };
    }
    
    private function numberToArabic($num)
    {
        $arabic = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
                   'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        
        if ($num < 20) return $arabic[$num];
        if ($num < 100) return ($num % 10 == 0 ? $tens[$num/10] : ($arabic[$num % 10] . ' و' . $tens[floor($num/10)]));
        if ($num < 1000) return $this->numberToArabic(floor($num/1000)) . ' ألف';
        if ($num < 1000000) return $this->numberToArabic(floor($num/1000)) . ' ألف';
        return number_format($num);
    }
}
