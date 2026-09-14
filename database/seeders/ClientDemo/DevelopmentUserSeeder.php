<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $dealer = DevelopmentDemoFixtures::require(Dealer::class, ['code' => 'MUT-001']);
        $accounts = [
            ['admin@mitsubishi.test', 'Development Admin', UserRole::Admin, null],
            ['dealer@mitsubishi.test', 'Development Dealer', UserRole::Dealer, $dealer->id],
            ['salesman@mitsubishi.test', 'Development Salesman', UserRole::Salesman, $dealer->id],
            ['customer@mitsubishi.test', 'Development Customer', UserRole::Customer, null],
            [DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'Demo Salesman - Arif Hasan', UserRole::Salesman, $dealer->id],
        ];
        foreach ($accounts as [$email, $name, $role, $dealerId]) {
            DevelopmentDemoFixtures::ensure(User::class, ['email' => $email], [
                'name' => $name, 'role' => $role, 'dealer_id' => $dealerId,
                'password' => Hash::make(DevelopmentDataSeeder::PASSWORD),
            ]);
        }
    }
}
