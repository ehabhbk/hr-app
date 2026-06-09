<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('avatar')->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->string('phone')->nullable()->after('is_active');
            $table->string('national_id')->nullable()->after('phone');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->string('module');
            $table->timestamps();
            
            $table->unique(['role_id', 'permission', 'module']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_number')->nullable()->after('file_number');
            $table->string('national_id')->nullable()->after('phone');
            $table->string('bank_name')->nullable()->after('national_id');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('nationality')->nullable()->after('bank_account');
            $table->date('birth_date')->nullable()->after('nationality');
            $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            $table->string('marital_status')->nullable()->after('gender');
            $table->decimal('transport_allowance', 12, 2)->default(0)->after('position_allowance');
            $table->decimal('housing_allowance', 12, 2)->default(0)->after('transport_allowance');
            $table->decimal('food_allowance', 12, 2)->default(0)->after('housing_allowance');
            $table->decimal('other_allowances', 12, 2)->default(0)->after('food_allowance');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('other_allowances');
            $table->decimal('social_insurance', 12, 2)->default(0)->after('tax_rate');
            $table->decimal('other_deductions', 12, 2)->default(0)->after('social_insurance');
            $table->enum('job_type', ['full_time', 'part_time', 'contract', 'temporary'])->default('full_time')->after('position');
            $table->string('contract_type')->nullable()->after('job_type');
            $table->date('contract_start')->nullable()->after('contract_type');
            $table->date('contract_end')->nullable()->after('contract_start');
            $table->text('duties')->nullable()->after('contract_end');
            $table->text('qualifications')->nullable()->after('duties');
            $table->text('experience')->nullable()->after('qualifications');
            $table->json('emergency_contacts')->nullable()->after('experience');
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
