<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'display_name',
        'description',
        'color',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function hasPermission($permission, $module = null)
    {
        $perms = $this->getAttributeValue('permissions') ?: [];
        if (in_array('*', $perms)) {
            return true;
        }

        if ($module) {
            return $this->permissions()
                ->where('permission', $permission)
                ->where('module', $module)
                ->exists();
        }

        return in_array($permission, $perms);
    }

    public function getDisplayNameAttribute()
    {
        // Check name_ar first, then display_name, then fallback to name
        return $this->name_ar ?? $this->attributes['display_name'] ?? $this->name;
    }

    public static function defaultRoles()
    {
        return [
            [
                'name' => 'admin',
                'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على النظام',
                'color' => '#dc2626',
                'permissions' => ['*'],
            ],
            [
                'name' => 'hr_manager',
                'display_name' => 'مدير الموارد البشرية',
                'description' => 'إدارة الموارد البشرية',
                'color' => '#7c3aed',
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
                    'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
                    'attendance.view', 'attendance.manage', 'attendance.sync',
                    'devices.view', 'devices.manage',
                    'leaves.view', 'leaves.approve', 'leaves.reject', 'leaves.manage',
                    'advances.view', 'advances.approve', 'advances.reject', 'advances.manage',
                    'warnings.view', 'warnings.create', 'warnings.delete', 'warnings.manage',
                    'incentives.view', 'incentives.manage',
                    'reports.view', 'reports.export', 'reports.print',
                    'letters.view', 'letters.create', 'letters.print', 'letters.delete',
                    'bank.view', 'bank.export',
                    'settings.view', 'settings.edit',
                    'notifications.view', 'notifications.send',
                ],
            ],
            [
                'name' => 'accountant',
                'display_name' => 'محاسب',
                'description' => 'إدارة الرواتب والمالية',
                'color' => '#059669',
                'permissions' => [
                    'employees.view',
                    'attendance.view',
                    'reports.view', 'reports.export', 'reports.salary', 'reports.tax',
                    'bank.view', 'bank.export',
                ],
            ],
            [
                'name' => 'department_manager',
                'display_name' => 'مدير قسم',
                'description' => 'إدارة القسم',
                'color' => '#0891b2',
                'permissions' => [
                    'employees.view',
                    'attendance.view',
                    'leaves.view', 'leaves.approve', 'leaves.reject',
                    'warnings.view', 'warnings.create',
                    'reports.view',
                ],
            ],
            [
                'name' => 'employee',
                'display_name' => 'موظف',
                'description' => 'صلاحيات الموظف العادي',
                'color' => '#6366f1',
                'permissions' => [
                    'profile.view', 'profile.edit',
                    'attendance.view_own',
                    'leaves.view_own', 'leaves.request',
                    'advances.view_own', 'advances.request',
                ],
            ],
        ];
    }
}
