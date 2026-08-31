<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DevelopmentUserSeeder extends Seeder
{
    /**
     * Seed local accounts used to manually verify authentication.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('Development users may only be seeded in the local environment.');
        }

        $users = [
            ['name' => 'Development Admin', 'email' => 'admin@mitsubishi.test', 'role' => UserRole::Admin],
            ['name' => 'Development Dealer', 'email' => 'dealer@mitsubishi.test', 'role' => UserRole::Dealer],
            ['name' => 'Development Salesman', 'email' => 'salesman@mitsubishi.test', 'role' => UserRole::Salesman],
            ['name' => 'Development Customer', 'email' => 'customer@mitsubishi.test', 'role' => UserRole::Customer],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                ],
            );
        }
    }
}
