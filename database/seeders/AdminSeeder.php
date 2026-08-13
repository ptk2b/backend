<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'Admin'],
            [
                'name'     => 'Administrator',
                'username' => 'Admin',
                'email'    => 'admin@ptk2b.com',
                'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'Secure!K2B#2026@Pass')),
            ]
        );
    }
}
