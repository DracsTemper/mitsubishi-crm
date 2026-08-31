<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'salesman_id' => User::factory()->state([
                'role' => UserRole::Salesman,
                'dealer_id' => Dealer::factory(),
            ]),
            'name' => fake()->name(),
            'phone' => fake()->numerify('000-14##-####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'status' => 'new',
        ];
    }
}
