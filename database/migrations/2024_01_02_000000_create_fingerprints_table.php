<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fingerprints')) {
            return;
        }
        Schema::create('fingerprints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('attendance_device_id')->nullable();
            $table->string('finger_id');
            $table->string('finger_position')->default('right');
            $table->string('finger')->default('thumb');
            $table->longText('template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['employee_id', 'finger_id', 'attendance_device_id'], 'unique_fingerprint');
        });

        // Add foreign keys after table creation (referenced tables may not exist yet)
        if (Schema::hasTable('employees')) {
            Schema::table('fingerprints', function (Blueprint $table) {
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
        if (Schema::hasTable('attendance_devices')) {
            Schema::table('fingerprints', function (Blueprint $table) {
                $table->foreign('attendance_device_id')->references('id')->on('attendance_devices')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprints');
    }
};
