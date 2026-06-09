<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('work_shifts', 'is_overnight')) {
                $table->boolean('is_overnight')->default(false)->after('end_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('work_shifts', 'is_overnight')) {
                $table->dropColumn('is_overnight');
            }
        });
    }
};
