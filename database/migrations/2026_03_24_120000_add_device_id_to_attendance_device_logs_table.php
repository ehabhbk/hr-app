<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_device_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('id');
            $table->foreign('device_id')->references('id')->on('attendance_devices')->onDelete('set null');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_device_logs', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
