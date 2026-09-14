<?php

namespace App\Console\Commands;

use Database\Seeders\ClientDemoSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareClientDemo extends Command
{
    protected $signature = 'demo:prepare';
    protected $description = 'Apply ordinary migrations and create fictional fixtures on the dedicated local demo database';

    public function handle(): int
    {
        // Validate resolved connection BEFORE any migration or database write.
        DevelopmentDemoSeeder::assertTarget();
        if ($this->call('migrate', ['--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $tables = ['dealers', 'users', 'vehicles', 'dealer_vehicle_allocations', 'customers', 'test_drive_slots', 'test_drives', 'bookings'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $result = $this->call('db:seed', ['--class' => ClientDemoSeeder::class, '--no-interaction' => true]);
        if ($result !== self::SUCCESS) {
            return $result;
        }
        $this->table(['Table', 'Created this run', 'Total'], collect($tables)->map(function ($table) use ($before) {
            $count = DB::table($table)->count();
            return [$table, $count - $before[$table], $count];
        }));
        return self::SUCCESS;
    }
}
