<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->string('period', 7)->after('total_score')->default(now()->format('Y-m'));
            $table->unique(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'period']);
            $table->dropColumn('period');
        });
    }
};
