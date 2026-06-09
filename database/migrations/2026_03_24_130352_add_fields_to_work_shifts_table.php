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
            $table->json('week_days')->nullable()->after('end_time');
            $table->json('weekend_days')->nullable()->after('week_days');
            $table->decimal('daily_hours', 5, 2)->nullable()->after('weekend_days');
            $table->boolean('active')->default(true)->after('daily_hours');
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
