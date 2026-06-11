<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
         |-----------------------------------------------------------------------
         | Departments
         |-----------------------------------------------------------------------
         */
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        /*
         |-----------------------------------------------------------------------
         | Employees
         |-----------------------------------------------------------------------
         |
         | Create employees after departments so we can safely add FK constraints.
         | Use column-level ->index() to avoid creating the same index twice.
         */
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();

                // ملف الموظف
                $table->string('file_number')->unique()->nullable();

                // بيانات أساسية
                $table->string('name');
                $table->string('email')->unique()->nullable();
                $table->string('phone')->nullable();

                // المسمى والبدل
                $table->string('position')->nullable();
                $table->string('position_grade')->nullable();
                $table->decimal('position_allowance', 12, 2)->default(0);

                // علاقة القسم (عمود unsignedBigInteger مع فهرس)
                $table->unsignedBigInteger('department_id')->nullable()->index();

                // جهاز البصمة ومرتبطة به
                $table->unsignedBigInteger('attendance_device_id')->nullable()->index();
                $table->string('device_user_id')->nullable()->index()->comment('رقم المستخدم على جهاز البصمة');

                // تواريخ وملفات
                $table->date('hire_date')->nullable();
                $table->string('cv')->nullable();
                $table->string('profile_photo')->nullable();
                $table->string('address')->nullable();

                // الحضور والغياب
                $table->integer('attendance_days')->default(0);
                $table->integer('absence_days')->default(0);
                $table->integer('late_arrivals')->default(0);
                $table->integer('early_leaves')->default(0);

                // الإجازات
                $table->integer('leave_count')->default(0);
                $table->integer('leave_duration')->default(0);
                $table->enum('leave_type', ['official', 'sick'])->nullable();
                $table->boolean('leave_paid')->default(true);

                // الرواتب
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->decimal('advance', 12, 2)->default(0);
                $table->decimal('gross_salary', 12, 2)->default(0);

                // إنضباط وإنذارات وحالة
                $table->decimal('discipline_rate', 5, 2)->default(100);
                $table->integer('warnings')->default(0);
                $table->enum('status', ['active', 'terminated', 'warning', 'vacation'])->default('active');

                $table->text('notes')->nullable();
                $table->timestamps();
            });

            // الآن أضف قيد المفتاح الأجنبي department_id بعد إنشاء employees وdepartments
            Schema::table('employees', function (Blueprint $table) {
                // تأكد من عدم وجود القيد مسبقًا
                try {
                    $table->foreign('department_id')
                          ->references('id')
                          ->on('departments')
                          ->onDelete('set null');
                } catch (\Throwable $e) {
                    // تجاهل إذا كان القيد موجودًا أو لا يمكن إضافته الآن
                }
            });
        }

        /*
         |-----------------------------------------------------------------------
         | Employee compensations
         |-----------------------------------------------------------------------
         */
        if (! Schema::hasTable('employee_compensations')) {
            Schema::create('employee_compensations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');

                $table->enum('type', ['allowance', 'incentive', 'advance', 'deduction'])->index();
                $table->string('title');
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('frequency', ['one_time', 'monthly', 'yearly'])->default('monthly');
                $table->boolean('active')->default(true);
                $table->date('applied_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'type']);
            });
        }

        /*
         |-----------------------------------------------------------------------
         | Employee contracts
         |-----------------------------------------------------------------------
         */
        if (! Schema::hasTable('employee_contracts')) {
            Schema::create('employee_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('contract_number')->nullable()->index();
                $table->string('position')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('duration_months')->nullable()->comment('مدة العقد بالأشهر');
                $table->enum('duration_unit', ['months', 'years'])->default('months');
                $table->enum('type', ['fixed_term','open_ended','temporary'])->default('fixed_term');
                $table->decimal('salary', 12, 2)->default(0);
                $table->text('salary_terms')->nullable();
                $table->enum('status', ['draft','active','expired','terminated'])->default('draft');
                $table->string('file_path')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id','status']);
            });

            // أضف القيد الأجنبي بعد إنشاء الجدول
            Schema::table('employee_contracts', function (Blueprint $table) {
                try {
                    $table->foreign('employee_id')
                          ->references('id')
                          ->on('employees')
                          ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // تجاهل الأخطاء إن وُجدت
                }
            });
        }

        /*
         |-----------------------------------------------------------------------
         | Work shifts and assignments
         |-----------------------------------------------------------------------
         */
        if (! Schema::hasTable('work_shifts')) {
            Schema::create('work_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_shift_assignments')) {
            Schema::create('employee_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('work_shift_id')->index();
                $table->date('date');
                $table->timestamps();

                $table->unique(['employee_id','date']);
                $table->index(['work_shift_id','date']);
            });

            // أضف قيود FK بعد إنشاء الجداول
            Schema::table('employee_shift_assignments', function (Blueprint $table) {
                try {
                    $table->foreign('employee_id')
                          ->references('id')
                          ->on('employees')
                          ->onDelete('cascade');
                } catch (\Throwable $e) {}

                try {
                    $table->foreign('work_shift_id')
                          ->references('id')
                          ->on('work_shifts')
                          ->onDelete('cascade');
                } catch (\Throwable $e) {}
            });
        }

        // Fix foreign keys on tables created before employees existed (e.g., deductions, fingerprints)
        if (Schema::hasTable('deductions') && Schema::hasColumn('deductions', 'employee_id')) {
            try {
                Schema::table('deductions', function (Blueprint $table) {
                    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('fingerprints') && Schema::hasColumn('fingerprints', 'employee_id')) {
            try {
                Schema::table('fingerprints', function (Blueprint $table) {
                    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table('fingerprints', function (Blueprint $table) {
                    $table->foreign('attendance_device_id')->references('id')->on('attendance_devices')->onDelete('set null');
                });
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // احذف القيود والجداول بالترتيب العكسي
        if (Schema::hasTable('employee_shift_assignments')) {
            Schema::table('employee_shift_assignments', function (Blueprint $table) {
                try { $table->dropForeign(['employee_id']); } catch (\Throwable $e) {}
                try { $table->dropForeign(['work_shift_id']); } catch (\Throwable $e) {}
            });
            Schema::dropIfExists('employee_shift_assignments');
        }

        if (Schema::hasTable('work_shifts')) {
            Schema::dropIfExists('work_shifts');
        }

        if (Schema::hasTable('employee_contracts')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                try { $table->dropForeign(['employee_id']); } catch (\Throwable $e) {}
            });
            Schema::dropIfExists('employee_contracts');
        }

        if (Schema::hasTable('employee_compensations')) {
            Schema::table('employee_compensations', function (Blueprint $table) {
                try { $table->dropForeign(['employee_id']); } catch (\Throwable $e) {}
            });
            Schema::dropIfExists('employee_compensations');
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                try { $table->dropForeign(['department_id']); } catch (\Throwable $e) {}
            });
            Schema::dropIfExists('employees');
        }

        if (Schema::hasTable('departments')) {
            Schema::dropIfExists('departments');
        }
    }
};