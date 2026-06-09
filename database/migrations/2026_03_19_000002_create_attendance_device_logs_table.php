<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_logs', function (Blueprint $table) {
            $table->id();

            // بيانات قادمة من جهاز ZKTeco
            $table->unsignedInteger('uid')->nullable(); // Unique internal uid on device
            $table->string('device_user_id'); // غالبًا رقم/كود المستخدم على الجهاز
            $table->unsignedInteger('state')->nullable(); // نوع الحدث/الحالة
            $table->timestamp('timestamp'); // وقت البصمة حسب الجهاز

            $table->json('raw')->nullable(); // payload كامل (للاستدلال/الديباج)

            $table->timestamps();

            $table->unique(['device_user_id', 'timestamp', 'state'], 'adl_unique_entry');
            $table->index(['timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_logs');
    }
};

