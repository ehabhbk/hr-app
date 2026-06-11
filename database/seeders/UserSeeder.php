<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate([
            'username' => 'admin',
        ], [
            'username' => 'admin',
            'full_name' => 'مدير النظام',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin'),
            'role_id' => 1,
        ]);
    }
}