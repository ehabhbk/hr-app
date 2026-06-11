<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        } else {
            // Ensure roles table has display_name column if created by another migration without it
            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'display_name')) {
                    $table->string('display_name')->after('name');
                }
            });
        }

        if (!Schema::hasTable('notifications')) {
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
        }

        if (!Schema::hasTable('bank_exports')) {
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
        }

        if (!Schema::hasTable('overtime_records')) {
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
        }

        if (!Schema::hasTable('late_deductions')) {
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
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('id')->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            $cols = ['bank_name', 'bank_branch', 'bank_account', 'nationality', 'contract_end_date', 'employment_type'];
            $existing = Schema::getColumnListing('employees');
            $needed = array_diff($cols, $existing);
            if (empty($needed)) return;

            if (in_array('bank_name', $needed)) $table->string('bank_name')->nullable()->after('base_salary');
            if (in_array('bank_branch', $needed)) $table->string('bank_branch')->nullable()->after('bank_name');
            if (in_array('bank_account', $needed)) $table->string('bank_account')->nullable()->after('bank_branch');
            if (in_array('nationality', $needed)) $table->string('nationality')->nullable()->after('bank_account');
            if (in_array('contract_end_date', $needed)) $table->date('contract_end_date')->nullable()->after('hire_date');
            if (in_array('employment_type', $needed)) $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'temporary', 'intern'])->default('full_time')->after('contract_end_date');
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
