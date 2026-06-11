<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\RolePermission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'name_ar' => 'مدير النظام',
                'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على النظام',
                'color' => '#dc2626',
                'permissions' => ['*'],
            ],
            [
                'name' => 'hr_manager',
                'name_ar' => 'مدير الموارد البشرية',
                'display_name' => 'مدير الموارد البشرية',
                'description' => 'إدارة الموارد البشرية',
                'color' => '#7c3aed',
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.edit', 'employees.delete', 'employees.terminate', 'employees.restore', 'employees.evaluate',
                    'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
                    'attendance.view', 'attendance.manage', 'attendance.sync', 'attendance.excuse',
                    'devices.view', 'devices.manage',
                    'leaves.view', 'leaves.approve', 'leaves.reject', 'leaves.manage', 'leaves.request',
                    'advances.view', 'advances.approve', 'advances.reject', 'advances.manage', 'advances.request',
                    'warnings.view', 'warnings.create', 'warnings.delete', 'warnings.manage',
                    'incentives.view', 'incentives.manage',
                    'reports.view', 'reports.export', 'reports.print', 'reports.dashboard', 'reports.salary', 'reports.income_tax', 'reports.salary_increase', 'reports.leaves_warnings', 'reports.employee', 'reports.evaluation', 'reports.department', 'reports.history', 'reports.letters',
                    'letters.view', 'letters.create', 'letters.print', 'letters.delete',
                    'bank.view', 'bank.export',
                    'assets.view', 'assets.manage', 'assets.return',
                    'settings.view', 'settings.edit', 'settings.organization', 'settings.attendance', 'settings.leaves', 'settings.advances', 'settings.shifts', 'settings.financials', 'settings.settlements', 'settings.whatsapp',
                    'notifications.view', 'notifications.send',
                    'roles.view',
                    'menu.dashboard', 'menu.employees', 'menu.departments', 'menu.fingerprint', 'menu.attendance', 'menu.bank', 'menu.reports', 'menu.settings',
                ],
            ],
            [
                'name' => 'accountant',
                'name_ar' => 'محاسب',
                'display_name' => 'محاسب',
                'description' => 'إدارة الرواتب والمالية',
                'color' => '#059669',
                'permissions' => [
                    'employees.view',
                    'attendance.view',
                    'reports.view', 'reports.export', 'reports.salary', 'reports.income_tax', 'reports.dashboard',
                    'bank.view', 'bank.export',
                    'settings.financials',
                    'menu.dashboard', 'menu.reports', 'menu.bank',
                ],
            ],
            [
                'name' => 'department_manager',
                'name_ar' => 'مدير قسم',
                'display_name' => 'مدير قسم',
                'description' => 'إدارة القسم',
                'color' => '#0891b2',
                'permissions' => [
                    'employees.view',
                    'attendance.view', 'attendance.excuse',
                    'leaves.view', 'leaves.approve', 'leaves.reject',
                    'warnings.view', 'warnings.create',
                    'reports.view', 'reports.dashboard', 'reports.department',
                    'menu.dashboard', 'menu.employees', 'menu.attendance',
                ],
            ],
            [
                'name' => 'department_supervisor',
                'name_ar' => 'مشرف قسم',
                'display_name' => 'مشرف قسم',
                'description' => 'مشرف على موظفي القسم',
                'color' => '#0891b2',
                'permissions' => [
                    'employees.view',
                    'attendance.view', 'attendance.excuse',
                    'leaves.view', 'leaves.approve', 'leaves.reject',
                    'warnings.view', 'warnings.create',
                    'reports.view', 'reports.dashboard',
                    'menu.dashboard', 'menu.employees', 'menu.attendance',
                ],
            ],
            [
                'name' => 'employee',
                'name_ar' => 'موظف',
                'display_name' => 'موظف',
                'description' => 'صلاحيات الموظف العادي',
                'color' => '#6366f1',
                'permissions' => [
                    'profile.view', 'profile.edit',
                    'attendance.view_own',
                    'leaves.view_own', 'leaves.request',
                    'advances.view_own', 'advances.request',
                    'reports.view', 'reports.dashboard',
                    'menu.dashboard', 'menu.employees',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            
            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            // Update permissions JSON field (for existing roles too)
            $role->update(['permissions' => $permissions]);

            // Clear existing role_permissions records and add new ones
            $role->permissions()->delete();
            
            foreach ($permissions as $perm) {
                $parts = explode('.', $perm);
                $role->permissions()->create([
                    'permission' => $perm,
                    'module' => $parts[0],
                ]);
            }
        }

        $this->command->info('Roles seeded successfully!');
    }
}
