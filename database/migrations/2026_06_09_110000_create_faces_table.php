<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_device_id')->nullable()->constrained()->onDelete('set null');
            $table->string('face_id')->comment('Face template ID on device (50-54)');
            $table->longText('template')->nullable()->comment('Face template data from device');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['employee_id', 'face_id', 'attendance_device_id'], 'unique_face');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faces');
    }
};
