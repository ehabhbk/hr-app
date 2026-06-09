<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_settings', function (Blueprint $table) {
            $table->id();

            // اتصال الجهاز
            $table->boolean('enabled')->default(false);
            $table->string('name')->default('Fingerprint Device');
            $table->string('host')->nullable(); // IP / hostname
            $table->unsignedInteger('port')->default(4370); // شائع في أجهزة ZKTeco

            // بروتوكول/نوع الجهاز (للتمديد لاحقًا)
            $table->string('driver')->default('zk'); // zk | soap | tcp | http ...

            // إعدادات اتصال عامة
            $table->unsignedInteger('timeout_ms')->default(3000);

            // إعدادات المزامنة (لاستخدام Cron/Queue لاحقًا)
            $table->unsignedInteger('sync_interval_seconds')->default(300);
            $table->timestamp('last_sync_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_settings');
    }
};

