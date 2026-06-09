<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // حذف قيد employees.department_id إن وُجد
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Exception $e) {
                    // تجاهل إذا لم يكن القيد موجودًا
                }
                // حذف الفهرس إن بقي
                try {
                    $table->dropIndex(['department_id']);
                } catch (\Exception $e) {
                    // تجاهل
                }
            });
        }

        // أضف هنا جداول أخرى إن كانت تشير إلى departments بنفس النمط

        // الآن احذف جدول departments بأمان
        if (Schema::hasTable('departments')) {
            Schema::dropIfExists('departments');
        }
    }

    public function down(): void
    {
        // إعادة إنشاء جدول departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // إعادة إضافة القيد إلى employees.department_id إن رغبت
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'department_id')) {
            Schema::table('employees', function (Blueprint $table) {
                try {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                } catch (\Exception $e) {
                    // تجاهل الأخطاء أثناء إعادة الإضافة
                }
            });
        }
    }
};