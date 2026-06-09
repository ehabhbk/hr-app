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
        Schema::table('work_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('work_shifts', 'week_days')) {
                $table->json('week_days')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('work_shifts', 'weekend_days')) {
                $table->json('weekend_days')->nullable()->after('week_days');
            }
            if (!Schema::hasColumn('work_shifts', 'daily_hours')) {
                $table->integer('daily_hours')->nullable()->default(8)->after('weekend_days');
            }
            if (!Schema::hasColumn('work_shifts', 'color')) {
                $table->string('color')->nullable()->default('#3B82F6')->after('daily_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            //
        });
    }
};
