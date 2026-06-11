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
        Schema::table('fingerprints', function (Blueprint $table) {
            if (!Schema::hasColumn('fingerprints', 'type')) {
                $table->string('type', 50)->default('fingerprint')->after('finger');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fingerprints', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
