<?php

namespace Database\Factories;

use App\Enums\TestDriveStatus;
use App\Models\Customer;
use App\Models\TestDrive;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TestDrive> */
class TestDriveFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();

        return [
            'customer_id' => $customer->id,
            'salesman_id' => $customer->salesman_id,
            'vehicle_id' => Vehicle::factory()->create([
                'dealer_id' => $customer->salesman->dealer_id,
            ])->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '15:00',
            'status' => TestDriveStatus::Scheduled,
            'notes' => fake()->sentence(),
        ];
    }
}
