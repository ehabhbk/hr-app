<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('termination_type', ['arbitrary', 'unjustified', 'mutual', 'performance', 'conduct', 'other'])->nullable()->after('status');
            $table->text('termination_reason')->nullable()->after('termination_type');
            $table->date('termination_date')->nullable()->after('termination_reason');
            $table->string('contract_file')->nullable()->after('cv');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['termination_type', 'termination_reason', 'termination_date', 'contract_file']);
        });
    }
};
