<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('offboardings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->enum('type', ['termination', 'resignation', 'retirement']);
            $table->date('last_working_date');
            $table->text('reason')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('settlement_done')->default(false);
            $table->boolean('assets_returned')->default(false);
            $table->boolean('access_revoked')->default(false);
            $table->boolean('exit_interview_done')->default(false);
            $table->text('exit_interview_notes')->nullable();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index('status');
        });
    }
    public function down(): void { Schema::dropIfExists('offboardings'); }
};
