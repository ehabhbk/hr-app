<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $all = Setting::all()->mapWithKeys(fn ($s) => [$s->key => $s->value]);

        return response()->json(['data' => $all]);
    }

    public function getCurrency()
    {
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];
        
        return response()->json([
            'data' => [
                'currency' => $orgData['currency'] ?? 'SDG',
                'currency_symbol' => $orgData['currency_symbol'] ?? 'جنيه',
            ]
        ]);
    }

    public static function getCurrencyInfo()
    {
        $org = Setting::where('key', 'organization')->first();
        $orgData = $org ? $org->value : [];
        
        return [
            'currency' => $orgData['currency'] ?? 'SDG',
            'currency_symbol' => $orgData['currency_symbol'] ?? 'جنيه',
        ];
    }

    public function show($key)
    {
        $s = Setting::where('key', $key)->first();

        return response()->json(['data' => $s ? $s->value : null]);
    }

    public function update(Request $request, $key)
    {
        $value = $request->all();
        $setting = Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        return response()->json(['data' => $setting->value]);
    }

    public function organization()
    {
        $org = Setting::where('key', 'organization')->first();
        $data = $org ? $org->value : [];

        if (!is_array($data)) {
            $data = [];
        }

        if (isset($data['logo']) && $data['logo']) {
            $data['logo_url'] = '/storage/' . $data['logo'];
        }
        if (isset($data['stamp']) && $data['stamp']) {
            $data['stamp_url'] = '/storage/' . $data['stamp'];
        }

        return response()->json(['data' => $data]);
    }

    public function updateOrganization(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Organization update: received data', $request->all());
        
        try {
            $data = [];
            
            $data['name'] = $request->input('name', '');
            $data['address'] = $request->input('address', '');
            $data['phone'] = $request->input('phone', '');
            $data['email'] = $request->input('email', '');
            $data['tax_number'] = $request->input('tax_number', '');
            $data['commercial_register'] = $request->input('commercial_register', '');
            $data['activity'] = $request->input('activity', '');
            $data['employee_count'] = $request->input('employee_count', '');
            $data['foundation_year'] = $request->input('foundation_year', '');
            $data['currency'] = $request->input('currency', 'SDG');
            $data['currency_symbol'] = $request->input('currency_symbol', 'جنيه');

            $existing = Setting::where('key', 'organization')->first();
            $existingData = $existing ? $existing->value : [];

            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $logoName = 'logos/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $logo->getClientOriginalName());
                $logo->move(storage_path('app/public/logos'), $logoName);
                $data['logo'] = $logoName;
            } else {
                $data['logo'] = $existingData['logo'] ?? null;
            }

            if ($request->hasFile('stamp')) {
                $stamp = $request->file('stamp');
                $stampName = 'stamps/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $stamp->getClientOriginalName());
                $stamp->move(storage_path('app/public/stamps'), $stampName);
                $data['stamp'] = $stampName;
            } else {
                $data['stamp'] = $existingData['stamp'] ?? null;
            }

            $setting = Setting::updateOrCreate(['key' => 'organization'], ['value' => $data]);

            $responseData = $setting->value;
            if ($responseData['logo']) {
                $responseData['logo_url'] = '/storage/' . $responseData['logo'];
            }
            if ($responseData['stamp']) {
                $responseData['stamp_url'] = '/storage/' . $responseData['stamp'];
            }

            return response()->json(['data' => $responseData, 'message' => 'تم الحفظ بنجاح']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Organization save error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateWhatsAppSettings(Request $request)
    {
        try {
            $data = [
                'enabled' => $request->boolean('enabled', false),
                'api_url' => $request->input('api_url', 'https://api.whatsapp.com/send'),
                'api_key' => $request->input('api_key', ''),
                'phone_number' => $request->input('phone_number', ''),
                'notify_phone' => $request->input('notify_phone', ''),
                'notify_on_warning' => $request->boolean('notify_on_warning', true),
                'notify_on_leave' => $request->boolean('notify_on_leave', true),
                'notify_on_advance' => $request->boolean('notify_on_advance', true),
                'notify_on_late' => $request->boolean('notify_on_late', true),
                'message_template_warning' => $request->input('message_template_warning', 'عزيزي {name}، تم إصدار إنذار رقم {warning_no} بسبب {reason}'),
                'message_template_leave' => $request->input('message_template_leave', 'عزيزي {name}، تم {action} طلب الإجازة المقدم من {from} إلى {to}'),
                'message_template_advance' => $request->input('message_template_advance', 'عزيزي {name}، تم {action} طلب السلفة بمبلغ {amount}'),
                'message_template_late' => $request->input('message_template_late', 'عزيزي {name}، تم تسجيل تأخير اليوم'),
                'notify_on_appointment' => $request->boolean('notify_on_appointment', true),
                'message_template_appointment' => $request->input('message_template_appointment', 'عزيزي {name}، يسعدنا إخباركم بانضمامكم إلينا في {company} بتاريخ {date}. الوظيفة: {position}، القسم: {department}، الراتب: {salary}'),
                'notify_on_leave_end' => $request->boolean('notify_on_leave_end', true),
                'message_template_leave_end' => $request->input('message_template_leave_end', 'عزيزي {name}، نذكّركم بأن إجازتكم ستنتهي غداً. نرجو العودة في الموعد المحدد.'),
            ];

            $setting = Setting::updateOrCreate(['key' => 'whatsapp'], ['value' => $data]);

            return response()->json([
                'data' => $setting->value,
                'message' => 'تم حفظ إعدادات واتساب بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getWhatsAppSettings()
    {
        $settings = Setting::where('key', 'whatsapp')->first();
        $data = $settings ? $settings->value : [
            'enabled' => false,
            'api_url' => 'https://api.whatsapp.com/send',
            'api_key' => '',
            'phone_number' => '',
            'notify_phone' => '',
            'notify_on_warning' => true,
            'notify_on_leave' => true,
            'notify_on_advance' => true,
            'notify_on_late' => true,
            'message_template_warning' => 'عزيزي {name}، تم إصدار إنذار رقم {warning_no} بسبب {reason}',
            'message_template_leave' => 'عزيزي {name}، تم {action} طلب الإجازة المقدم من {from} إلى {to}',
            'message_template_advance' => 'عزيزي {name}، تم {action} طلب السلفة بمبلغ {amount}',
            'message_template_late' => 'عزيزي {name}، تم تسجيل تأخير اليوم',
            'notify_on_appointment' => true,
            'message_template_appointment' => 'عزيزي {name}، يسعدنا إخباركم بانضمامكم إلينا في {company} بتاريخ {date}. الوظيفة: {position}، القسم: {department}، الراتب: {salary}',
            'notify_on_leave_end' => true,
            'message_template_leave_end' => 'عزيزي {name}، نذكّركم بأن إجازتكم ستنتهي غداً. نرجو العودة في الموعد المحدد.',
        ];

        return response()->json(['data' => $data]);
    }

    public function testWhatsApp(Request $request)
    {
        try {
            $phone = $request->input('phone');
            $message = $request->input('message', 'اختبار من نظام Jawda HR');

            $whatsapp = new \App\Services\WhatsAppService();
            $result = $whatsapp->sendMessage($phone, $message);

            if ($result) {
                return response()->json(['message' => 'تم إرسال رسالة الاختبار بنجاح']);
            } else {
                return response()->json(['error' => 'فشل إرسال الرسالة. تأكد من إعدادات الواتساب'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function audits()
    {
        return response()->json(['data' => []]);
    }

    public function getAttendanceSettings()
    {
        $settings = Setting::where('key', 'attendance')->first();
        $data = $settings ? $settings->value : [
            'shift_ids' => [],
            'allowed_delay_minutes' => 5,
            'delay_before_warning' => 2,
            'delay_before_deduction' => 5,
            'late_deduction_percent' => 1.5,
            'absence_after_minutes' => 60,
            'absence_deduction_days' => 1,
            'termination_after_days' => 30,
        ];

        return response()->json(['data' => $data]);
    }

    public function updateAttendanceSettings(Request $request)
    {
        $data = $request->all();
        Setting::updateOrCreate(['key' => 'attendance'], ['value' => $data]);
        $this->logActivity('attendance_settings_updated', null, null, $data, 'تحديث إعدادات الحضور', $request);
        
        // Recalculate all attendance records with new settings
        $records = \App\Models\AttendanceRecord::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();
        
        $attendanceController = new \App\Http\Controllers\AttendanceRecordController();
        foreach ($records as $record) {
            $attendanceController->calculateDeductions($record);
        }
        
        return response()->json([
            'data' => $data, 
            'message' => 'تم الحفظ',
            'recalculated' => $records->count() . ' سجل',
        ]);
    }

    public function getSalarySettings()
    {
        $settings = Setting::where('key', 'financials')->first();
        $data = $settings ? $settings->value : [
            'default_currency' => 'SDG',
            'currency_symbol' => 'جنيه سوداني',
            'include_transport' => true,
            'include_housing' => true,
            'include_food' => true,
            'insurance_rate' => 0,
            'insurance_ceiling' => 0,
        ];

        return response()->json(['data' => $data]);
    }

    public function updateSalarySettings(Request $request)
    {
        $data = $request->all();
        Setting::updateOrCreate(['key' => 'financials'], ['value' => $data]);
        $this->logActivity('salary_settings_updated', null, null, $data, 'تحديث إعدادات المرتبات', $request);
        return response()->json(['data' => $data, 'message' => 'تم الحفظ']);
    }

    public function getTaxBrackets()
    {
        $raw = DB::table('settings')->where('key', 'tax-brackets')->value('value');
        $data = null;
        if ($raw !== null) {
            if (is_array($raw)) {
                $data = $raw;
            } else {
                $decoded = json_decode($raw, true);
                $data = is_array($decoded) ? $decoded : null;
            }
            // backward compatibility: unwrap {brackets: [...]} from old saves
            if (is_array($data) && isset($data['brackets'])) {
                $data = $data['brackets'];
            }
        }
        return response()->json(['data' => $data]);
    }

    public function updateTaxBrackets(Request $request)
    {
        $data = $request->input('brackets', []);
        Setting::updateOrCreate(['key' => 'tax-brackets'], ['value' => $data]);
        return response()->json(['data' => $data, 'message' => 'تم الحفظ']);
    }

    public function getLeaveSettings()
    {
        $settings = Setting::where('key', 'leaves')->first();
        $data = $settings ? $settings->value : [
            'annual_days' => 21,
            'sick_days' => 10,
            'maternity_days' => 90,
            'hajj_days' => 14,
            'unpaid_leave_max_days' => 30,
            'notice_days' => 3,
            'by_grade' => [],
        ];

        return response()->json(['data' => $data]);
    }

    public function updateLeaveSettings(Request $request)
    {
        $data = $request->all();
        Setting::updateOrCreate(['key' => 'leaves'], ['value' => $data]);
        $this->logActivity('leave_settings_updated', null, null, $data, 'تحديث إعدادات الإجازات', $request);
        return response()->json(['data' => $data, 'message' => 'تم الحفظ']);
    }

    public function getAdvanceSettings()
    {
        $settings = Setting::where('key', 'advances')->first();
        $data = $settings ? $settings->value : [
            'enabled' => true,
            'short_advance' => [
                'enabled' => true,
                'max_percent' => 50,
                'min_service_months' => 0,
            ],
            'long_advance' => [
                'enabled' => true,
                'max_amount' => 500000,
                'min_amount' => 10000,
                'min_service_months' => 6,
                'max_installments' => 12,
                'min_installments' => 3,
            ],
        ];

        return response()->json(['data' => $data]);
    }

    public function updateAdvanceSettings(Request $request)
    {
        $data = $request->all();
        Setting::updateOrCreate(['key' => 'advances'], ['value' => $data]);
        $this->logActivity('advance_settings_updated', null, null, $data, 'تحديث إعدادات السلف', $request);
        return response()->json(['data' => $data, 'message' => 'تم الحفظ']);
    }

    public function getSalaryIncrease()
    {
        $settings = Setting::where('key', 'salary-increase')->first();
        $data = $settings ? $settings->value : [
            'default_percent' => 10,
            'per_employee' => [],
            'apply_automatically' => false,
            'min_service_months' => 12,
        ];

        return response()->json(['data' => $data]);
    }

    public function updateSalaryIncrease(Request $request)
    {
        $data = $request->all();
        Setting::updateOrCreate(['key' => 'salary-increase'], ['value' => $data]);
        return response()->json(['data' => $data, 'message' => 'تم الحفظ']);
    }

    public function getAllSettings()
    {
        $settings = Setting::all()->mapWithKeys(fn($s) => [$s->key => $s->value]);
        
        $defaults = [
            'attendance' => [
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
            ],
            'financials' => [
                'default_currency' => 'SDG',
                'currency_symbol' => 'جنيه سوداني',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return response()->json(['data' => $settings]);
    }
}
