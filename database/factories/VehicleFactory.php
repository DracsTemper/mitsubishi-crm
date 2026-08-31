<?php

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\Vehicle;
use App\Models\DealerVehicleAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Vehicle $vehicle): void {
            if ($vehicle->dealer_id) {
                DealerVehicleAllocation::query()->firstOrCreate(
                    ['dealer_id' => $vehicle->dealer_id, 'vehicle_id' => $vehicle->id],
                    ['quantity' => 1, 'status' => 'active'],
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dealer_id' => Dealer::factory(),
            'name' => fake()->randomElement(['Outlander', 'Xforce', 'Triton', 'Pajero Sport']),
            'variant' => fake()->randomElement(['2.4 AWD', '1.5 Premium', '2.4 Double Cab', '2.4 Elite']),
            'model_year' => 2026,
            'color' => fake()->randomElement(['Graphite Gray', 'Pearl White', 'Red Diamond', 'Jet Black']),
            'price' => fake()->numberBetween(3850000, 6250000),
            'description' => fake()->sentence(),
            'image' => 'assets/images/vehicles/outlander-2026.png',
            'status' => Vehicle::STATUSES[0],
        ];
    }
}
