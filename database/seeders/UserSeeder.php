<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@example.com'], // unique key
            [
                'full_name' => 'Super Admin',
                'password'  => 'password123', // will be auto-hashed by your mutator
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
