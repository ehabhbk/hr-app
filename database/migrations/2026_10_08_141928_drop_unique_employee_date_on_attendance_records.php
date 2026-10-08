<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * السماح بعدة جلسات حضور/انصراف للموظف في اليوم الواحد —
     * كل جلسة صف مستقل ولا تُكتب فوق سابقتها أبداً.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        // قد يفشل إن وُجدت صفوف مكررة لليوم (ناتجة عن الجلسات المتعددة)
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unique(['employee_id', 'date']);
        });
    }
};
