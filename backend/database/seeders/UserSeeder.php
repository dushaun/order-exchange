<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'email' => 'user1@test.com',
                'name' => 'Test User 1',
                'password' => Hash::make('password'),
                'balance' => '10000.00000000',
            ],
            [
                'email' => 'user2@test.com',
                'name' => 'Test User 2',
                'password' => Hash::make('password'),
                'balance' => '10000.00000000',
            ],
        ];

        foreach ($demoUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'balance' => $userData['balance'],
                ]
            );
        }
    }
}
