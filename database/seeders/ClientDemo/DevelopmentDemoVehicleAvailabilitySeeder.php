<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use Illuminate\Database\Seeder;

class DevelopmentDemoVehicleAvailabilitySeeder extends Seeder
{
    public const VEHICLE_NAMES = ['Outlander', 'Xforce', 'Triton', 'Pajero Sport'];

    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $dealer = DevelopmentDemoFixtures::require(Dealer::class, ['code' => 'MUT-001']);
        foreach (self::VEHICLE_NAMES as $name) {
            $vehicle = DevelopmentVehicleSeeder::vehicle($name);
            DevelopmentDemoFixtures::ensure(DealerVehicleAllocation::class,
                ['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id],
                ['quantity' => $name === 'Pajero Sport' ? 1 : 2, 'status' => 'active'],
            );
        }
    }
}
