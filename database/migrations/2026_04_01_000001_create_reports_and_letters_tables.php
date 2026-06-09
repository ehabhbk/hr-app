<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('report_type');
            $table->string('title');
            $table->json('parameters')->nullable();
            $table->json('filters')->nullable();
            $table->enum('status', ['generated', 'printed', 'exported'])->default('generated');
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index(['report_type', 'generated_at']);
        });

        Schema::create('letter_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            $table->string('letter_type');
            $table->string('title');
            $table->string('reference_number')->unique();
            $table->json('parameters')->nullable();
            $table->text('content')->nullable();
            $table->enum('status', ['draft', 'approved', 'printed', 'sent'])->default('draft');
            $table->timestamp('generated_at');
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            
            $table->index(['letter_type', 'employee_id']);
        });

        Schema::create('salary_increases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->decimal('old_salary', 12, 2);
            $table->decimal('new_salary', 12, 2);
            $table->decimal('increase_amount', 12, 2);
            $table->decimal('increase_percent', 5, 2);
            $table->string('reason')->nullable();
            $table->date('effective_date');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('approved');
            $table->timestamps();
            
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_incentives', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_loan_payments', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('income_tax', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'month', 'year']);
            $table->index(['month', 'year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('salary_increases');
        Schema::dropIfExists('letter_logs');
        Schema::dropIfExists('report_logs');
    }
};
