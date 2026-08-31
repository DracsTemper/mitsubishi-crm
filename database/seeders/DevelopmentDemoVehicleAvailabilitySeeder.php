<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\DealerVehicleAllocation;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentDemoVehicleAvailabilitySeeder extends Seeder
{
    public const VEHICLE_NAMES = ['Outlander', 'Xforce', 'Triton', 'Pajero Sport'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo Vehicle availability may only be seeded locally or in tests.');
        }

        $salesman = User::query()->where('email', DevelopmentTestDriveSeeder::SALESMAN_EMAIL)->firstOrFail();
        if (! $salesman->dealer_id) {
            throw new LogicException('The demo Salesman must belong to a Dealer before Vehicle availability is seeded.');
        }

        $vehicles = Vehicle::query()->whereIn('name', self::VEHICLE_NAMES)->get()->keyBy('name');
        foreach (self::VEHICLE_NAMES as $name) {
            $vehicle = $vehicles->get($name);
            if (! $vehicle) {
                throw new LogicException("Required demo Vehicle {$name} does not exist.");
            }
            DealerVehicleAllocation::query()->updateOrCreate(
                ['dealer_id' => $salesman->dealer_id, 'vehicle_id' => $vehicle->id],
                ['quantity' => $name === 'Pajero Sport' ? 1 : 2, 'status' => 'active'],
            );
        }
    }
}
