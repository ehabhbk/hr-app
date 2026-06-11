<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('avatar')->constrained('roles')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role_id');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'national_id')) {
                $table->string('national_id')->nullable()->after('phone');
            }
        });

        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->string('permission');
                $table->string('module');
                $table->timestamps();
                
                $table->unique(['role_id', 'permission', 'module']);
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $cols = [
                'employee_number', 'national_id', 'bank_name', 'bank_account',
                'nationality', 'birth_date', 'gender', 'marital_status',
                'transport_allowance', 'housing_allowance', 'food_allowance',
                'other_allowances', 'tax_rate', 'social_insurance', 'other_deductions',
                'job_type', 'contract_type', 'contract_start', 'contract_end',
                'duties', 'qualifications', 'experience', 'emergency_contacts'
            ];
            $existing = Schema::getColumnListing('employees');
            $needed = array_diff($cols, $existing);
            if (empty($needed)) return;

            if (in_array('employee_number', $needed)) $table->string('employee_number')->nullable()->after('file_number');
            if (in_array('national_id', $needed)) $table->string('national_id')->nullable()->after('phone');
            if (in_array('bank_name', $needed)) $table->string('bank_name')->nullable()->after('national_id');
            if (in_array('bank_account', $needed)) $table->string('bank_account')->nullable()->after('bank_name');
            if (in_array('nationality', $needed)) $table->string('nationality')->nullable()->after('bank_account');
            if (in_array('birth_date', $needed)) $table->date('birth_date')->nullable()->after('nationality');
            if (in_array('gender', $needed)) $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            if (in_array('marital_status', $needed)) $table->string('marital_status')->nullable()->after('gender');
            if (in_array('transport_allowance', $needed)) $table->decimal('transport_allowance', 12, 2)->default(0)->after('position_allowance');
            if (in_array('housing_allowance', $needed)) $table->decimal('housing_allowance', 12, 2)->default(0)->after('transport_allowance');
            if (in_array('food_allowance', $needed)) $table->decimal('food_allowance', 12, 2)->default(0)->after('housing_allowance');
            if (in_array('other_allowances', $needed)) $table->decimal('other_allowances', 12, 2)->default(0)->after('food_allowance');
            if (in_array('tax_rate', $needed)) $table->decimal('tax_rate', 5, 2)->default(0)->after('other_allowances');
            if (in_array('social_insurance', $needed)) $table->decimal('social_insurance', 12, 2)->default(0)->after('tax_rate');
            if (in_array('other_deductions', $needed)) $table->decimal('other_deductions', 12, 2)->default(0)->after('social_insurance');
            if (in_array('job_type', $needed)) $table->enum('job_type', ['full_time', 'part_time', 'contract', 'temporary'])->default('full_time')->after('position');
            if (in_array('contract_type', $needed)) $table->string('contract_type')->nullable()->after('job_type');
            if (in_array('contract_start', $needed)) $table->date('contract_start')->nullable()->after('contract_type');
            if (in_array('contract_end', $needed)) $table->date('contract_end')->nullable()->after('contract_start');
            if (in_array('duties', $needed)) $table->text('duties')->nullable()->after('contract_end');
            if (in_array('qualifications', $needed)) $table->text('qualifications')->nullable()->after('duties');
            if (in_array('experience', $needed)) $table->text('experience')->nullable()->after('qualifications');
            if (in_array('emergency_contacts', $needed)) $table->json('emergency_contacts')->nullable()->after('experience');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_number', 'national_id', 'bank_name', 'bank_account', 
                'nationality', 'birth_date', 'gender', 'marital_status',
                'transport_allowance', 'housing_allowance', 'food_allowance', 
                'other_allowances', 'tax_rate', 'social_insurance', 'other_deductions',
                'job_type', 'contract_type', 'contract_start', 'contract_end',
                'duties', 'qualifications', 'experience', 'emergency_contacts'
            ]);
        });

        Schema::dropIfExists('role_permissions');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active', 'phone', 'national_id']);
        });
    }
};
