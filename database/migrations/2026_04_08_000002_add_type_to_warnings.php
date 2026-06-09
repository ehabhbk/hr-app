<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            if (!Schema::hasColumn('warnings', 'type')) {
                $table->enum('type', ['written', 'final'])->default('written')->after('reason');
            }
            if (!Schema::hasColumn('warnings', 'status')) {
                $table->enum('status', ['active', 'cancelled'])->default('active')->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            $table->dropColumn(['type', 'status']);
        });
    }
};