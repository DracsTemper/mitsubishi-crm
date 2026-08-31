<?php

namespace Database\Seeders;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\TestDrive;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DevelopmentTestDriveSeeder extends Seeder
{
    public const SALESMAN_EMAIL = 'demo.salesman.testdrive@example.test';
    public const CUSTOMER_EMAIL = 'demo.rahim.testdrive@example.test';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Test Drive fixtures may only be seeded locally or in tests.');
        }

        $vehicle = Vehicle::query()
            ->where('name', 'Outlander')
            ->orderByDesc('model_year')
            ->first()
            ?? Vehicle::query()->with('dealer')->orderBy('id')->first();

        if (! $vehicle) {
            throw new LogicException('Seed the existing Vehicle inventory before Test Drive demo data.');
        }

        $salesman = User::query()->updateOrCreate(
            ['email' => self::SALESMAN_EMAIL],
            [
                'name' => 'Demo Salesman - Arif Hasan',
                'dealer_id' => $vehicle->dealer_id,
                'password' => Hash::make(DevelopmentDataSeeder::PASSWORD),
                'role' => UserRole::Salesman,
            ],
        );

        $customer = Customer::query()->updateOrCreate(
            ['email' => self::CUSTOMER_EMAIL],
            [
                'salesman_id' => $salesman->id,
                'name' => 'Demo Customer - Rahim Ahmed',
                'phone' => '000-DEMO-3201',
                'address' => 'Demonstration record — not a real Customer address',
                'city' => 'Dhaka (Demo)',
                'status' => 'active',
            ],
        );

        TestDrive::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'notes' => '[DEMO-F32] Initial Outlander Test Drive'],
            [
                'vehicle_id' => $vehicle->id,
                'salesman_id' => $salesman->id,
                'scheduled_date' => DevelopmentTestDriveSlotSeeder::demoDates()[0],
                'scheduled_time' => '15:00',
                'status' => TestDriveStatus::Scheduled,
            ],
        );
    }
}
