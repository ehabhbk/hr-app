<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('is_absent')->default(false)->after('warning_id');
            $table->boolean('absence_excused')->default(false)->after('is_absent');
            $table->string('absence_excuse_reason')->nullable()->after('absence_excused');
            $table->decimal('absence_deduction', 10, 2)->default(0)->after('absence_excuse_reason');
            $table->decimal('absence_days', 5, 2)->default(0)->after('absence_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['is_absent', 'absence_excused', 'absence_excuse_reason', 'absence_deduction', 'absence_days']);
        });
    }
};
