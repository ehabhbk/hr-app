<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();

            // معلومات الجهاز
            $table->string('name');                 // اسم الجهاز
            $table->string('host');                 // IP أو hostname
            $table->integer('port')->default(4370);
            $table->string('driver')->default('zk'); // نوع البروتوكول
            $table->boolean('enabled')->default(true);

            // بيانات تعريفية إضافية
            $table->string('device_id')->nullable()->index(); // رقم الجهاز إن وجد
            $table->string('password')->nullable();           // كلمة مرور الجهاز إن وجد

            // تتبع آخر مزامنة
            $table->timestamp('last_sync_at')->nullable()->index();

            $table->timestamps();

            // فهارس مفيدة للبحث والاتصال
            $table->index(['host', 'port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};