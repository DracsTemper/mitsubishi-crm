<?php

namespace Database\Factories;

use App\Enums\DealerStatus;
use App\Models\Dealer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dealer> */
class DealerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Mitsubishi '.fake()->unique()->numerify('Test Branch ###'),
            'code' => fake()->unique()->bothify('TEST-###??'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'status' => DealerStatus::Active,
        ];
    }
}
