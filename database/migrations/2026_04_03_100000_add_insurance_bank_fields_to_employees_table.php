<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'fingerprint_id')) {
                $table->string('fingerprint_id')->nullable()->after('position');
            }
            if (!Schema::hasColumn('employees', 'insurance_type')) {
                $table->enum('insurance_type', ['none', 'health', 'social', 'both'])->default('none')->after('fingerprint_id');
            }
            if (!Schema::hasColumn('employees', 'insurance_amount')) {
                $table->decimal('insurance_amount', 10, 2)->default(0)->after('insurance_type');
            }
            if (!Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('insurance_amount');
            }
            if (!Schema::hasColumn('employees', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'bank_account')) {
                $table->dropColumn('bank_account');
            }
            if (Schema::hasColumn('employees', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('employees', 'insurance_amount')) {
                $table->dropColumn('insurance_amount');
            }
            if (Schema::hasColumn('employees', 'insurance_type')) {
                $table->dropColumn('insurance_type');
            }
            if (Schema::hasColumn('employees', 'fingerprint_id')) {
                $table->dropColumn('fingerprint_id');
            }
        });
    }
};
