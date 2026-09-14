<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DevelopmentDemoVehicleAllocationSeeder extends Seeder
{
    public const EXTRA_MODELS = [
        ['name' => 'Eclipse Cross', 'variant' => '1.5 Turbo', 'model_year' => 2026, 'color' => 'Red Diamond', 'price' => 5200000, 'description' => 'Compact Mitsubishi crossover presented as a demonstration catalog model.', 'image' => 'assets/images/vehicles/xforce-2026.png', 'status' => 'available'],
        ['name' => 'Outlander Sport', 'variant' => '2.0 ES', 'model_year' => 2026, 'color' => 'Pearl White', 'price' => 4300000, 'description' => 'Compact sport utility model for demo catalog presentation.', 'image' => 'assets/images/vehicles/outlander-2026.png', 'status' => 'available'],
        ['name' => 'Triton Ralliart', 'variant' => '2.4 Ralliart Demo', 'model_year' => 2026, 'color' => 'White Diamond', 'price' => 5400000, 'description' => 'Ralliart-branded Triton demonstration model; demo allocation only.', 'image' => 'assets/images/vehicles/triton-2026.png', 'status' => 'test_drive'],
        ['name' => 'Lancer Evolution', 'variant' => 'Evolution X Historical', 'model_year' => 2016, 'color' => 'Rally Red', 'price' => 0, 'description' => 'Historical discontinued Mitsubishi performance model shown only as a catalog demonstration example.', 'image' => null, 'status' => 'test_drive'],
    ];

    public const PLAN = [
        'MUT-001' => ['Outlander' => 2, 'Xforce' => 2, 'Triton' => 2, 'Pajero Sport' => 1, 'Eclipse Cross' => 1, 'Outlander Sport' => 1, 'Triton Ralliart' => 1],
        'TEST-F13-GUL' => ['Outlander' => 2, 'Xforce' => 1, 'Eclipse Cross' => 1],
        'MDM-002' => ['Outlander' => 1, 'Triton' => 1, 'Pajero Sport' => 2, 'Triton Ralliart' => 1],
        'TEST-F13-DHN' => ['Pajero Sport' => 1],
        'TEST-F13-UTT' => ['Outlander' => 1],
    ];

    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $uttara = DevelopmentDemoFixtures::require(Dealer::class, ['code' => 'MUT-001']);
        foreach (self::EXTRA_MODELS as $attributes) {
            DevelopmentDemoFixtures::ensure(Vehicle::class,
                ['name' => $attributes['name'], 'variant' => $attributes['variant'], 'model_year' => $attributes['model_year']],
                ['dealer_id' => $uttara->id, ...$attributes],
            );
        }
        foreach (self::PLAN as $code => $models) {
            $dealer = DevelopmentDemoFixtures::require(Dealer::class, ['code' => $code]);
            foreach ($models as $name => $quantity) {
                $vehicle = DevelopmentVehicleSeeder::vehicle($name);
                DevelopmentDemoFixtures::ensure(DealerVehicleAllocation::class,
                    ['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id],
                    ['quantity' => $quantity, 'status' => 'active'],
                );
            }
        }
    }
}
