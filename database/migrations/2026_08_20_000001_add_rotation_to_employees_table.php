<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->json('rotation_shift_ids')->nullable()->after('work_shift_id');
            $table->date('rotation_start_date')->nullable()->after('rotation_shift_ids');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['rotation_shift_ids', 'rotation_start_date']);
        });
    }
};
