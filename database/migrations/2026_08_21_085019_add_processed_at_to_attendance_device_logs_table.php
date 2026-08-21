<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_device_logs', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('raw');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_device_logs', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
