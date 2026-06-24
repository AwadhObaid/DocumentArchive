<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'مدير النظام',
                'email' => 'admin@archive.local',
                'phone' => null,
                'role' => 'admin',
                'is_active' => true,
                'password' => Hash::make('12345678'),
            ]
        );
    }
}
