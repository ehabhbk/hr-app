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
        Schema::table('warnings', function (Blueprint $table) {
            if (!Schema::hasColumn('warnings', 'type')) {
                $table->string('type')->default('written')->after('employee_id');
            }
            if (!Schema::hasColumn('warnings', 'date')) {
                $table->date('date')->nullable()->after('type');
            }
            if (!Schema::hasColumn('warnings', 'status')) {
                $table->string('status')->default('active')->after('date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            //
        });
    }
};
