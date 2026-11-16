<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@blugre.test'],
            ['name' => 'Blugre Admin', 'role' => 'admin', 'password' => 'password123']
        );

        User::firstOrCreate(
            ['email' => 'cashier@blugre.test'],
            ['name' => 'Blugre Cashier', 'role' => 'cashier', 'password' => 'password123']
        );
    }
}
