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
        Schema::table('advances_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('advances_requests', 'type')) {
                $table->string('type')->default('short')->after('status');
            }
            if (!Schema::hasColumn('advances_requests', 'installments')) {
                $table->integer('installments')->default(1)->after('type');
            }
            if (!Schema::hasColumn('advances_requests', 'date')) {
                $table->date('date')->nullable()->after('installments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advances_requests', function (Blueprint $table) {
            //
        });
    }
};
