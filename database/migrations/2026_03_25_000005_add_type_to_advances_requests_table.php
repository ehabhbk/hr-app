<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advances_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('advances_requests', 'type')) {
                $table->enum('type', ['short', 'long'])->default('short')->after('amount');
            }
            if (! Schema::hasColumn('advances_requests', 'installments')) {
                $table->integer('installments')->default(1)->after('type');
            }
            if (! Schema::hasColumn('advances_requests', 'date')) {
                $table->date('date')->nullable()->after('installments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('advances_requests', function (Blueprint $table) {
            $table->dropColumn(['type', 'installments']);
        });
    }
};
