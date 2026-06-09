<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar',
        'role_id',
        'department_id',
        'is_active',
        'phone',
        'national_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function isDepartmentSupervisor(): bool
    {
        return $this->role && $this->role->name === 'department_supervisor' && $this->department_id;
    }

    public function hasPermission($permission, $module = null)
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($permission, $module);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role && $this->role->name === 'admin';
    }

    public function isHrManager(): bool
    {
        return $this->role && $this->role->name === 'hr_manager';
    }

    public function isAccountant(): bool
    {
        return $this->role && $this->role->name === 'accountant';
    }
}
