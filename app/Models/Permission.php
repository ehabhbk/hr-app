<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'module',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public static function getModules()
    {
        return [
            'employees' => 'الموظفين',
            'attendance' => 'الحضور والانصراف',
            'salaries' => 'المرتبات',
            'reports' => 'التقارير',
            'settings' => 'الإعدادات',
            'users' => 'المستخدمين',
            'devices' => 'أجهزة البصمة',
            'departments' => 'الأقسام',
            'allowances' => 'البدلات والحوافز',
            'leaves' => 'الإجازات',
            'warnings' => 'الإنذارات',
            'loans' => 'السلف',
            'letters' => 'الخطابات',
            'visibility' => 'ظهور العناصر',
        ];
    }

    public static function getDefaultPermissions()
    {
        return [
            // الموظفين
            ['name' => 'employees.view', 'display_name' => 'عرض الموظفين', 'module' => 'employees'],
            ['name' => 'employees.create', 'display_name' => 'إضافة موظف', 'module' => 'employees'],
            ['name' => 'employees.edit', 'display_name' => 'تعديل موظف', 'module' => 'employees'],
            ['name' => 'employees.delete', 'display_name' => 'حذف موظف', 'module' => 'employees'],
            ['name' => 'employees.export', 'display_name' => 'تصدير بيانات الموظفين', 'module' => 'employees'],

            // الحضور
            ['name' => 'attendance.view', 'display_name' => 'عرض الحضور', 'module' => 'attendance'],
            ['name' => 'attendance.manage', 'display_name' => 'إدارة الحضور', 'module' => 'attendance'],
            ['name' => 'attendance.export', 'display_name' => 'تصدير سجلات الحضور', 'module' => 'attendance'],

            // المرتبات
            ['name' => 'salaries.view', 'display_name' => 'عرض المرتبات', 'module' => 'salaries'],
            ['name' => 'salaries.manage', 'display_name' => 'إدارة المرتبات', 'module' => 'salaries'],
            ['name' => 'salaries.export', 'display_name' => 'تصدير المرتبات', 'module' => 'salaries'],
            ['name' => 'salaries.process', 'display_name' => 'معالجة المرتبات', 'module' => 'salaries'],

            // التقارير
            ['name' => 'reports.view', 'display_name' => 'عرض التقارير', 'module' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'تصدير التقارير', 'module' => 'reports'],

            // الإعدادات
            ['name' => 'settings.view', 'display_name' => 'عرض الإعدادات', 'module' => 'settings'],
            ['name' => 'settings.manage', 'display_name' => 'إدارة الإعدادات', 'module' => 'settings'],

            // المستخدمين
            ['name' => 'users.view', 'display_name' => 'عرض المستخدمين', 'module' => 'users'],
            ['name' => 'users.create', 'display_name' => 'إضافة مستخدم', 'module' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'تعديل مستخدم', 'module' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'حذف مستخدم', 'module' => 'users'],

            // الأجهزة
            ['name' => 'devices.view', 'display_name' => 'عرض الأجهزة', 'module' => 'devices'],
            ['name' => 'devices.manage', 'display_name' => 'إدارة الأجهزة', 'module' => 'devices'],

            // الأقسام
            ['name' => 'departments.view', 'display_name' => 'عرض الأقسام', 'module' => 'departments'],
            ['name' => 'departments.manage', 'display_name' => 'إدارة الأقسام', 'module' => 'departments'],

            // البدلات
            ['name' => 'allowances.view', 'display_name' => 'عرض البدلات', 'module' => 'allowances'],
            ['name' => 'allowances.manage', 'display_name' => 'إدارة البدلات', 'module' => 'allowances'],

            // الإجازات
            ['name' => 'leaves.view', 'display_name' => 'عرض الإجازات', 'module' => 'leaves'],
            ['name' => 'leaves.manage', 'display_name' => 'إدارة الإجازات', 'module' => 'leaves'],
            ['name' => 'leaves.approve', 'display_name' => 'موافقة على إجازات', 'module' => 'leaves'],

            // الإنذارات
            ['name' => 'warnings.view', 'display_name' => 'عرض الإنذارات', 'module' => 'warnings'],
            ['name' => 'warnings.manage', 'display_name' => 'إدارة الإنذارات', 'module' => 'warnings'],

            // السلف
            ['name' => 'loans.view', 'display_name' => 'عرض السلف', 'module' => 'loans'],
            ['name' => 'loans.manage', 'display_name' => 'إدارة السلف', 'module' => 'loans'],
            ['name' => 'loans.approve', 'display_name' => 'موافقة على سلف', 'module' => 'loans'],

            // الخطابات
            ['name' => 'letters.view', 'display_name' => 'عرض الخطابات', 'module' => 'letters'],
            ['name' => 'letters.create', 'display_name' => 'إنشاء خطاب', 'module' => 'letters'],
            ['name' => 'letters.export', 'display_name' => 'تصدير الخطابات', 'module' => 'letters'],

            // الصلاحيات
            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار', 'module' => 'roles'],
            ['name' => 'roles.manage', 'display_name' => 'إدارة الأدوار والصلاحيات', 'module' => 'roles'],

            // ظهور العناصر في القائمة
            ['name' => 'menu.dashboard', 'display_name' => 'ظهور لوحة التحكم', 'module' => 'visibility'],
            ['name' => 'menu.employees', 'display_name' => 'ظهور الموظفين', 'module' => 'visibility'],
            ['name' => 'menu.departments', 'display_name' => 'ظهور الأقسام', 'module' => 'visibility'],
            ['name' => 'menu.devices', 'display_name' => 'ظهور أجهزة البصمة', 'module' => 'visibility'],
            ['name' => 'menu.attendance', 'display_name' => 'ظهور سجل الحضور', 'module' => 'visibility'],
            ['name' => 'menu.bank_export', 'display_name' => 'ظهور التصدير البنكي', 'module' => 'visibility'],
            ['name' => 'menu.reports', 'display_name' => 'ظهور التقارير', 'module' => 'visibility'],
            ['name' => 'menu.settings', 'display_name' => 'ظهور الإعدادات', 'module' => 'visibility'],
            ['name' => 'menu.notifications', 'display_name' => 'ظهور الإشعارات', 'module' => 'visibility'],

            // ظهور تبويبات الإعدادات
            ['name' => 'tab.organization', 'display_name' => 'ظهور معلومات المؤسسة', 'module' => 'visibility'],
            ['name' => 'tab.attendance_settings', 'display_name' => 'ظهور إعدادات الحضور', 'module' => 'visibility'],
            ['name' => 'tab.leaves_settings', 'display_name' => 'ظهور إعدادات الإجازات', 'module' => 'visibility'],
            ['name' => 'tab.advances_settings', 'display_name' => 'ظهور إعدادات السلف', 'module' => 'visibility'],
            ['name' => 'tab.shifts', 'display_name' => 'ظهور الورديات', 'module' => 'visibility'],
            ['name' => 'tab.financials', 'display_name' => 'ظهور المالية', 'module' => 'visibility'],
            ['name' => 'tab.whatsapp', 'display_name' => 'ظهور واتساب', 'module' => 'visibility'],
            ['name' => 'tab.roles', 'display_name' => 'ظهور الصلاحيات والمستخدمين', 'module' => 'visibility'],

            // بصمة الوجه والإصبع
            ['name' => 'biometric.view', 'display_name' => 'عرض بيانات البصمة', 'module' => 'biometric'],
            ['name' => 'biometric.manage', 'display_name' => 'إدارة البصمات والوجه', 'module' => 'biometric'],
            ['name' => 'menu.biometric', 'display_name' => 'ظهور إدارة البصمات', 'module' => 'visibility'],

            // ظهور تبويبات التقارير
            ['name' => 'tab.reports_salary', 'display_name' => 'ظهور تقرير المرتبات', 'module' => 'visibility'],
            ['name' => 'tab.reports_income_tax', 'display_name' => 'ظهور تقرير ضريبة الدخل', 'module' => 'visibility'],
            ['name' => 'tab.reports_leave_warning', 'display_name' => 'ظهور تقرير تحذير الإجازات', 'module' => 'visibility'],
            ['name' => 'tab.reports_department', 'display_name' => 'ظهور تقرير الأقسام', 'module' => 'visibility'],
            ['name' => 'tab.reports_employee', 'display_name' => 'ظهور تقرير تقييم الموظف', 'module' => 'visibility'],
            ['name' => 'tab.reports_summary', 'display_name' => 'ظهور ملخص التقارير', 'module' => 'visibility'],
            ['name' => 'tab.reports_salary_increase', 'display_name' => 'ظهور تقرير زيادة المرتبات', 'module' => 'visibility'],
            ['name' => 'tab.reports_letters', 'display_name' => 'ظهور سجل الخطابات', 'module' => 'visibility'],
        ];
    }
}
