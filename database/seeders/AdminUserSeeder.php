<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Admin One',
                'email' => 'admin@kidsimwatch.ge',
                'password' => 'password123',
            ],
            [
                'name' => 'Admin Two',
                'email' => 'admin2@kidsimwatch.ge',
                'password' => 'password123',
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => Hash::make($admin['password']),
                    'is_admin' => true,
                ]
            );
        }
    }
}
