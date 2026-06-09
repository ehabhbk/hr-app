<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_device_id')->nullable()->constrained()->onDelete('set null');
            $table->string('finger_id');
            $table->string('finger_position')->default('right');
            $table->string('finger')->default('thumb');
            $table->longText('template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['employee_id', 'finger_id', 'attendance_device_id'], 'unique_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprints');
    }
};
