<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Booking;
use App\Models\TestDrive;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentBookingSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $drive = DevelopmentDemoFixtures::require(TestDrive::class, ['notes' => DevelopmentTestDriveSeeder::NOTES[0]]);
        if ($drive->status->value !== 'completed' || $drive->outcome?->value !== 'booking') {
            throw new LogicException('Demo Booking requires a completed Test Drive with a booking decision.');
        }
        $dates = DevelopmentTestDriveSlotSeeder::demoDates();
        DevelopmentDemoFixtures::ensure(Booking::class, ['test_drive_id' => $drive->id], [
            'customer_id' => $drive->customer_id, 'vehicle_id' => $drive->vehicle_id, 'salesman_id' => $drive->salesman_id,
            'booking_date' => $drive->scheduled_date->toDateString(),
            'expected_delivery_date' => DevelopmentTestDriveSlotSeeder::workingDate($dates[2]->copy()->addDays(18))->toDateString(),
            'booking_amount' => 500000, 'status' => 'pending',
            'notes' => '[DEMO-F33] Customer said YES after completed Test Drive.',
        ]);
    }
}
