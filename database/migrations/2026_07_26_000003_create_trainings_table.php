<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('course_name');
            $table->string('institution')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('certificate_expiry')->nullable();
            $table->string('certificate_file')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['ongoing', 'completed', 'expired'])->default('ongoing');
            $table->timestamps();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('certificate_expiry');
            $table->index('status');
        });
    }
    public function down(): void { Schema::dropIfExists('trainings'); }
};
