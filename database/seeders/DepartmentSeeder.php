<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(['name' => 'الموارد البشرية'], ['description' => 'قسم إدارة الموظفين']);
        Department::firstOrCreate(['name' => 'المالية'], ['description' => 'قسم الحسابات والمرتبات']);
    }
}