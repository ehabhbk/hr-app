<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->enum('status', ['unread', 'read', 'archived'])->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });

        Schema::create('bank_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->string('bank_name');
            $table->decimal('total_amount', 15, 2);
            $table->integer('employee_count');
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['month', 'year']);
        });

        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('late_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('minutes');
            $table->decimal('deduction_amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'applied'])->default('pending');
            $table->timestamps();
            
            $table->unique(['employee_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('base_salary');
            $table->string('bank_branch')->nullable()->after('bank_name');
            $table->string('bank_account')->nullable()->after('bank_branch');
            $table->string('nationality')->nullable()->after('national_id');
            $table->date('contract_end_date')->nullable()->after('hire_date');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'temporary', 'intern'])->default('full_time')->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_branch', 'bank_account', 'nationality', 'contract_end_date', 'employment_type']);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active']);
        });
        
        Schema::dropIfExists('late_deductions');
        Schema::dropIfExists('overtime_records');
        Schema::dropIfExists('bank_exports');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('roles');
    }
};
