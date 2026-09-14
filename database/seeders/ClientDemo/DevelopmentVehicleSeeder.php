<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Dealer;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentVehicleSeeder extends Seeder
{
    /** @var list<array<string, int|string>> */
    public const VEHICLES = [
        ['dealer_code' => 'MUT-001', 'name' => 'Outlander', 'variant' => '2.4 AWD', 'model_year' => 2026, 'color' => 'Graphite Gray', 'price' => 5000000, 'image' => 'assets/images/vehicles/outlander-2026.png', 'status' => 'reserved'],
        ['dealer_code' => 'TEST-F13-GUL', 'name' => 'Xforce', 'variant' => '1.5 Premium', 'model_year' => 2026, 'color' => 'Pearl White', 'price' => 3850000, 'image' => 'assets/images/vehicles/xforce-2026.png', 'status' => 'available'],
        ['dealer_code' => 'MDM-002', 'name' => 'Triton', 'variant' => '2.4 Double Cab', 'model_year' => 2026, 'color' => 'Red Diamond', 'price' => 4600000, 'image' => 'assets/images/vehicles/triton-2026.png', 'status' => 'sold'],
        ['dealer_code' => 'TEST-F13-DHN', 'name' => 'Pajero Sport', 'variant' => '2.4 Elite', 'model_year' => 2026, 'color' => 'Jet Black', 'price' => 6250000, 'image' => 'assets/images/vehicles/pajero-sport-2026.png', 'status' => 'test_drive'],
    ];

    public static function vehicle(string $name): Vehicle
    {
        $definitions = [...self::VEHICLES, ...DevelopmentDemoVehicleAllocationSeeder::EXTRA_MODELS];
        foreach ($definitions as $definition) {
            if ($definition['name'] === $name) {
                return DevelopmentDemoFixtures::require(Vehicle::class, [
                    'name' => $name, 'variant' => $definition['variant'], 'model_year' => $definition['model_year'],
                ]);
            }
        }
        throw new LogicException('Unknown demo vehicle identity: '.$name);
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Vehicle fixtures may only be seeded locally or in tests.');
        }

        foreach (self::VEHICLES as $attributes) {
            $dealer = Dealer::query()->where('code', $attributes['dealer_code'])->first();
            if (! $dealer) {
                throw new LogicException("Required development Dealer {$attributes['dealer_code']} does not exist.");
            }
            unset($attributes['dealer_code']);
            DevelopmentDemoFixtures::ensure(Vehicle::class,
                ['name' => $attributes['name'], 'variant' => $attributes['variant'], 'model_year' => $attributes['model_year']],
                ['dealer_id' => $dealer->id, ...$attributes],
            );
        }
    }
}
