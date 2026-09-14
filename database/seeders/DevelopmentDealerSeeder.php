<?php

namespace Database\Seeders;

use App\Models\Dealer;
use Illuminate\Database\Seeder;

class DevelopmentDealerSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        foreach (['MUT-001' => 'Mitsubishi Uttara', 'MDM-002' => 'Mitsubishi Dhanmondi'] as $code => $name) {
            DevelopmentDemoFixtures::ensure(Dealer::class, ['code' => $code], [
                'name' => $name, 'city' => 'Dhaka', 'status' => 'active',
                'email' => strtolower($code).'@example.test',
            ]);
        }
    }
}
