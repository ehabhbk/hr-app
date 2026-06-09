<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advances_requests', function (Blueprint $table) {
            $table->decimal('remaining_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('remaining_amount');
        });
    }

    public function down(): void
    {
        Schema::table('advances_requests', function (Blueprint $table) {
            $table->dropColumn(['remaining_amount', 'paid_amount']);
        });
    }
};
