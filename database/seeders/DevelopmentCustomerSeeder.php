<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentCustomerSeeder extends Seeder
{
    public const CUSTOMERS = [
        [DevelopmentTestDriveSeeder::CUSTOMER_EMAIL, 'Demo Customer - Rahim Ahmed', DevelopmentTestDriveSeeder::SALESMAN_EMAIL],
        ['demo.nusrat@example.test', 'Demo Customer - Nusrat Jahan', DevelopmentTestDriveSeeder::SALESMAN_EMAIL],
        ['demo.sabbir@example.test', 'Demo Customer - Sabbir Hasan', 'salesman@mitsubishi.test'],
        ['demo.gulshan@example.test', 'Demo Customer - Gulshan', 'salesman.gulshan.01@example.test'],
    ];

    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        foreach (self::CUSTOMERS as $index => [$email, $name, $ownerEmail]) {
            $salesman = DevelopmentDemoFixtures::require(User::class, ['email' => $ownerEmail]);
            DevelopmentDemoFixtures::ensure(Customer::class, ['email' => $email], [
                'name' => $name, 'salesman_id' => $salesman->id,
                'phone' => '000-DEMO-'.(3201 + $index), 'city' => 'Dhaka (Demo)',
                'address' => 'Demonstration record — not a real Customer address', 'status' => 'active',
            ]);
        }
    }
}
