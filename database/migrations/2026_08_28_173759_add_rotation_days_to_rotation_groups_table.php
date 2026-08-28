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
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->unsignedInteger('rotation_days')->default(1)->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rotation_groups', function (Blueprint $table) {
            $table->dropColumn('rotation_days');
        });
    }
};
