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
                'password' => Hash::make('k2b123'),
            ]
        );
    }
}
