<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentDemoVehicleAllocationSeeder extends Seeder
{
    private const EXTRA_MODELS = [
        ['name' => 'Eclipse Cross', 'variant' => '1.5 Turbo', 'model_year' => 2026, 'color' => 'Red Diamond', 'price' => 5200000, 'description' => 'Compact Mitsubishi crossover presented as a demonstration catalog model.', 'image' => 'assets/images/vehicles/xforce-2026.png', 'status' => 'available'],
        ['name' => 'Outlander Sport', 'variant' => '2.0 ES', 'model_year' => 2026, 'color' => 'Pearl White', 'price' => 4300000, 'description' => 'Compact sport utility model for demo catalog presentation.', 'image' => 'assets/images/vehicles/outlander-2026.png', 'status' => 'available'],
        ['name' => 'Triton Ralliart', 'variant' => '2.4 Ralliart Demo', 'model_year' => 2026, 'color' => 'White Diamond', 'price' => 5400000, 'description' => 'Ralliart-branded Triton demonstration model; demo allocation only.', 'image' => 'assets/images/vehicles/triton-2026.png', 'status' => 'test_drive'],
        ['name' => 'Lancer Evolution', 'variant' => 'Evolution X Historical', 'model_year' => 2016, 'color' => 'Rally Red', 'price' => 0, 'description' => 'Historical discontinued Mitsubishi performance model shown only as a catalog demonstration example.', 'image' => null, 'status' => 'test_drive'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Demo Vehicle Allocations may only be seeded locally or in tests.');
        }

        $salesman = \App\Models\User::where('email', DevelopmentTestDriveSeeder::SALESMAN_EMAIL)->firstOrFail();
        $uttara = $salesman->dealer()->firstOrFail();
        foreach (self::EXTRA_MODELS as $attributes) {
            Vehicle::query()->firstOrCreate(
                ['name' => $attributes['name'], 'variant' => $attributes['variant'], 'model_year' => $attributes['model_year']],
                ['dealer_id' => $uttara->id, ...$attributes],
            );
        }

        $this->call(DevelopmentDemoVehicleAvailabilitySeeder::class);
        $dealers = Dealer::query()->get();
        $vehicles = Vehicle::query()->get()->keyBy('name');
        $plan = [
            $uttara->code => ['Outlander' => 2, 'Xforce' => 2, 'Triton' => 2, 'Pajero Sport' => 1, 'Eclipse Cross' => 1, 'Outlander Sport' => 1, 'Triton Ralliart' => 1],
        ];
        $gulshan = $dealers->first(fn ($dealer) => str_contains(strtolower($dealer->name), 'gulshan'));
        $dhanmondi = $dealers->first(fn ($dealer) => str_contains(strtolower($dealer->name), 'dhanmondi'));
        if ($gulshan) $plan[$gulshan->code] = ['Outlander' => 2, 'Xforce' => 1, 'Eclipse Cross' => 1];
        if ($dhanmondi) $plan[$dhanmondi->code] = ['Outlander' => 1, 'Triton' => 1, 'Pajero Sport' => 2, 'Triton Ralliart' => 1];

        foreach ($plan as $dealerCode => $models) {
            $dealer = $dealers->firstWhere('code', $dealerCode);
            foreach ($models as $name => $quantity) {
                DealerVehicleAllocation::query()->updateOrCreate(
                    ['dealer_id' => $dealer->id, 'vehicle_id' => $vehicles->get($name)->id],
                    ['quantity' => $quantity, 'status' => 'active'],
                );
            }
        }
    }
}
