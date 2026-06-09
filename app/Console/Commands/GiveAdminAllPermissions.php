<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;

class GiveAdminAllPermissions extends Command
{
    protected $signature = 'admin:give-all-permissions';
    protected $description = 'Give admin role all permissions';

    public function handle(): int
    {
        $admin = Role::where('name', 'admin')->first();
        
        if (!$admin) {
            $this->error('Admin role not found');
            return 1;
        }

        $allPerms = Permission::pluck('name')->toArray();
        $admin->permissions = json_encode($allPerms);
        $admin->save();
        
        $this->info("Admin now has " . count($allPerms) . " permissions");
        return 0;
    }
}
