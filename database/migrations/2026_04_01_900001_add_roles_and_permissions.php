<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create roles table first
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_ar');
                $table->string('description')->nullable();
                $table->string('color')->default('#6366f1');
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        // Ensure roles table has name_ar and color columns (in case created by another migration without them)
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'color')) {
                $table->string('color')->default('#6366f1')->after('description');
            }
        });

        // Create role_permissions table
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

        // Add role to users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('avatar')->constrained('roles')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role_id');
            }
        });

        // Add employee number and bank info to employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'employee_number')) {
                $table->string('employee_number')->nullable()->after('file_number');
            }
            if (!Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('address');
            }
            if (!Schema::hasColumn('employees', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('employees', 'national_id')) {
                $table->string('national_id')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('employees', 'transport_allowance')) {
                $table->decimal('transport_allowance', 12, 2)->default(0)->after('position_allowance');
            }
            if (!Schema::hasColumn('employees', 'housing_allowance')) {
                $table->decimal('housing_allowance', 12, 2)->default(0)->after('transport_allowance');
            }
            if (!Schema::hasColumn('employees', 'food_allowance')) {
                $table->decimal('food_allowance', 12, 2)->default(0)->after('housing_allowance');
            }
            if (!Schema::hasColumn('employees', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('food_allowance');
            }
            if (!Schema::hasColumn('employees', 'social_insurance')) {
                $table->decimal('social_insurance', 12, 2)->default(0)->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_number', 'bank_name', 'bank_account', 
                'national_id', 'transport_allowance', 
                'housing_allowance', 'food_allowance', 
                'tax_rate', 'social_insurance'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_active']);
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
