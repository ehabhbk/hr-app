<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class MenuTabPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = Permission::getDefaultPermissions();

        $count = 0;
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                $perm
            );
            $count++;
        }

        echo "Synced {$count} permissions from model\n";
    }
}
