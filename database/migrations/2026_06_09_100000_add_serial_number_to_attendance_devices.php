<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_devices', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('password')->index();
            }
            if (!Schema::hasColumn('attendance_devices', 'supports_face')) {
                $table->boolean('supports_face')->default(false)->after('serial_number');
            }
            if (!Schema::hasColumn('attendance_devices', 'supports_fingerprint')) {
                $table->boolean('supports_fingerprint')->default(true)->after('supports_face');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'supports_face', 'supports_fingerprint']);
        });
    }
};
