<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        
        // Insert default settlement settings
        DB::table('settlement_settings')->insert([
            'key' => 'service_end_bonus',
            'value' => json_encode([
                'enabled' => true,
                'months_per_year' => 0, // شهر لكل سنة خدمة (0 = بدون مكافأة)
                'max_months' => 0, // الحد الأقصى للأشهر
                'description' => 'مكافأة نهاية الخدمة - شهر لكل سنة خدمة',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('settlement_settings')->insert([
            'key' => 'notice_period',
            'value' => json_encode([
                'enabled' => true,
                'min_days' => 30, // أيام الإخطار minimum
                'by_service_years' => [
                    '0-5' => 30,  // أقل من 5 سنوات = 30 يوم
                    '5-10' => 60, // من 5 إلى 10 سنوات = 60 يوم
                    '10+' => 90,   // أكثر من 10 سنوات = 90 يوم
                ],
                'description' => 'فترة الإخطار المسبقة',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('settlement_settings')->insert([
            'key' => 'annual_leave_encashment',
            'value' => json_encode([
                'enabled' => true,
                'max_days_per_year' => 0, // 0 = غير محدود
                'min_service_months' => 0, // الحد الأدنى لاشهر الخدمة
                'description' => 'استبدال الإجازات السنوية نقداً',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('settlement_settings')->insert([
            'key' => 'severance_pay',
            'value' => json_encode([
                'enabled' => true,
                'first_5_years_months' => 1, // الشهر الأول = شهر لكل سنة (أقل من 5 سنوات)
                'after_5_years_months' => 2, // بعد 5 سنوات = شهرين لكل سنة
                'max_years' => 12, // الحد الأقصى 12 سنة
                'description' => 'مكافأة إنهاء الخدمة حسب قانون العمل',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('settlement_settings')->insert([
            'key' => 'ticket_allowance',
            'value' => json_encode([
                'enabled' => true,
                'amount_per_year' => 0, // المبلغ السنوي للتذكرة
                'description' => 'بدل تذكرة السفر السنوي',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_settings');
    }
};
