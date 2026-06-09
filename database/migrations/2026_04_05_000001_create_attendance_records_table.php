<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            
            // Check-in details
            $table->timestamp('check_in_time')->nullable();
            $table->string('check_in_type')->nullable(); // early, on_time, late
            $table->integer('check_in_delay_minutes')->default(0);
            $table->boolean('check_in_excused')->default(false);
            $table->string('check_in_excuse_reason')->nullable();
            
            // Check-out details
            $table->timestamp('check_out_time')->nullable();
            $table->string('check_out_type')->nullable(); // early, on_time, late
            $table->integer('check_out_early_minutes')->default(0);
            $table->boolean('check_out_excused')->default(false);
            $table->string('check_out_excuse_reason')->nullable();
            
            // Work hours
            $table->decimal('worked_hours', 5, 2)->default(0);
            $table->decimal('expected_hours', 5, 2)->default(0);
            
            // Deductions & warnings
            $table->boolean('has_delay')->default(false);
            $table->boolean('delay_excused')->default(false);
            $table->decimal('delay_deduction', 10, 2)->default(0);
            $table->decimal('early_leave_deduction', 10, 2)->default(0);
            $table->decimal('total_deduction', 10, 2)->default(0);
            $table->boolean('deduction_applied')->default(false);
            $table->boolean('warning_issued')->default(false);
            $table->foreignId('warning_id')->nullable()->constrained('warnings')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->unique(['employee_id', 'date']);
            $table->index(['date']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfColumns('attendance_records', [
            'check_in_type', 'check_in_delay_minutes', 'check_in_excused', 'check_in_excuse_reason',
            'check_out_type', 'check_out_early_minutes', 'check_out_excused', 'check_out_excuse_reason',
            'delay_excused', 'delay_deduction', 'early_leave_deduction', 'total_deduction',
            'deduction_applied', 'warning_issued', 'warning_id', 'notes'
        ]);
        Schema::dropIfExists('attendance_records');
    }
};
