<?php

namespace Database\Seeders;

use Database\Seeders\ClientDemo\DevelopmentBookingSeeder;
use Database\Seeders\ClientDemo\DevelopmentDataSeeder;
use Database\Seeders\ClientDemo\DevelopmentDemoVehicleAllocationSeeder;
use Database\Seeders\ClientDemo\DevelopmentDemoVehicleAvailabilitySeeder;
use Database\Seeders\ClientDemo\DevelopmentTestDriveSeeder;
use Database\Seeders\ClientDemo\DevelopmentTestDriveSlotSeeder;
use Database\Seeders\ClientDemo\DevelopmentUserSeeder;
use Database\Seeders\ClientDemo\DevelopmentVehicleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DevelopmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        self::assertTarget();

        // Data only: all inserts roll back together if a prerequisite/conflict fails.
        DB::transaction(function (): void {
            $this->call([
                DevelopmentDealerSeeder::class,
                DevelopmentDataSeeder::class,
                DevelopmentUserSeeder::class,
                DevelopmentVehicleSeeder::class,
                DevelopmentDemoVehicleAllocationSeeder::class,
                DevelopmentDemoVehicleAvailabilitySeeder::class,
                DevelopmentCustomerSeeder::class,
                DevelopmentTestDriveSlotSeeder::class,
                DevelopmentTestDriveSeeder::class,
                DevelopmentBookingSeeder::class,
            ]);
        });
    }

    public static function assertTarget(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development demo data is restricted to local/testing environments.');
        }
        $connection = DB::connection();
        $localDemo = app()->environment('local')
            && $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && $connection->getConfig('read') === null
            && $connection->getConfig('write') === null
            && $connection->getDatabaseName() === 'mitsubishi_crm_demo';
        $isolatedTest = app()->environment('testing')
            && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';
        if (! $localDemo && ! $isolatedTest) {
            throw new LogicException('Demo seeding requires local MySQL mitsubishi_crm_demo or testing SQLite :memory:.');
        }

    }
}
