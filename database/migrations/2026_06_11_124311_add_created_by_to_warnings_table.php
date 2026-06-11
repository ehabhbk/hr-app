<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            if (!Schema::hasColumn('warnings', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            if (Schema::hasColumn('warnings', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
