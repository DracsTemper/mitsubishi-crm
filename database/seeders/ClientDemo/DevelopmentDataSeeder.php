<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Enums\DealerStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DevelopmentDataSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    /** @var array<string, array{name: string, code: string, phone: string, email: string, address: string, city: string}> */
    public const DEALERS = [
        'uttara' => [
            'name' => 'Development Mitsubishi Uttara',
            'code' => 'TEST-F13-UTT',
            'phone' => '000-1300-0001',
            'email' => 'fixture.uttara@example.test',
            'address' => '13 Test Avenue, Fixture Sector 1',
            'city' => 'Dhaka',
        ],
        'dhanmondi' => [
            'name' => 'Development Mitsubishi Dhanmondi',
            'code' => 'TEST-F13-DHN',
            'phone' => '000-1300-0002',
            'email' => 'fixture.dhanmondi@example.test',
            'address' => '13 Test Road, Fixture Area 2',
            'city' => 'Dhaka',
        ],
        'gulshan' => [
            'name' => 'Development Mitsubishi Gulshan',
            'code' => 'TEST-F13-GUL',
            'phone' => '000-1300-0003',
            'email' => 'fixture.gulshan@example.test',
            'address' => '13 Test Circle, Fixture Area 3',
            'city' => 'Dhaka',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development fixture data may only be seeded locally or in tests.');
        }

        $password = Hash::make(self::PASSWORD);

        foreach (self::DEALERS as $key => $attributes) {
            $dealer = DevelopmentDemoFixtures::ensure(Dealer::class,
                ['code' => $attributes['code']],
                [...$attributes, 'status' => DealerStatus::Active],
            );

            for ($number = 1; $number <= 2; $number++) {
                $this->createFixtureUser(
                    sprintf('Fixture %s Dealer User %02d', ucfirst($key), $number),
                    sprintf('dealer.%s.%02d@example.test', $key, $number),
                    UserRole::Dealer,
                    $dealer,
                    $password,
                );
            }

            for ($number = 1; $number <= 3; $number++) {
                $this->createFixtureUser(
                    sprintf('Fixture %s Salesman %02d', ucfirst($key), $number),
                    sprintf('salesman.%s.%02d@example.test', $key, $number),
                    UserRole::Salesman,
                    $dealer,
                    $password,
                );
            }
        }

        $this->createFixtureUser(
            'Fixture Unassigned Dealer User',
            'dealer.unassigned@example.test',
            UserRole::Dealer,
            null,
            $password,
        );

        $this->createFixtureUser(
            'Fixture Unassigned Salesman',
            'salesman.unassigned@example.test',
            UserRole::Salesman,
            null,
            $password,
        );
    }

    private function createFixtureUser(
        string $name,
        string $email,
        UserRole $role,
        ?Dealer $dealer,
        string $password,
    ): void {
        DevelopmentDemoFixtures::ensure(User::class,
            ['email' => $email],
            [
                'name' => $name,
                'dealer_id' => $dealer?->id,
                'password' => $password,
                'role' => $role,
            ],
        );
    }
}
