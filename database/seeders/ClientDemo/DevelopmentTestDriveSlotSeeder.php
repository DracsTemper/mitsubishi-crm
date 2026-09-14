<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Services\BusinessCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use LogicException;

class DevelopmentTestDriveSlotSeeder extends Seeder
{
    public static function workingDate(Carbon $from, int $direction = 1): Carbon
    {
        $date = $from->copy()->startOfDay();
        $calendar = app(BusinessCalendar::class);
        for ($attempt = 0; $attempt < 366; $attempt++) {
            if ($calendar->isOpen($date)) {
                return $date;
            }
            $date->addDays($direction);
        }
        throw new LogicException('No working date within 366 days; check the business calendar.');
    }

    /** Past booked, past ready-to-book, future scheduled, past follow-up example. */
    public static function demoDates(): array
    {
        $recent = self::workingDate(today()->subDay(), -1);
        $previous = self::workingDate($recent->copy()->subDay(), -1);
        $defaults = [
            $previous,
            $recent,
            self::workingDate(today()->addDays(2)),
            self::workingDate($previous->copy()->subDay(), -1),
        ];
        foreach (DevelopmentTestDriveSeeder::NOTES as $index => $notes) {
            $matches = TestDrive::query()->where('notes', $notes)->limit(2)->get();
            if ($matches->count() > 1) {
                throw new LogicException('Ambiguous demo Test Drive marker: '.$notes);
            }
            // Retain the original dates on later runs; never move existing appointments.
            if ($matches->isNotEmpty()) {
                $defaults[$index] = $matches->first()->scheduled_date->copy();
            }
            if (! app(BusinessCalendar::class)->isOpen($defaults[$index])) {
                throw new LogicException('An existing demo date is now closed; no appointment was moved.');
            }
        }

        return $defaults;
    }

    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $templates = app(BusinessCalendar::class)->slotTemplates();
        if ($templates === []) {
            throw new LogicException('The business calendar has no Test Drive slot templates.');
        }
        $dates = self::demoDates();
        foreach (DevelopmentDemoVehicleAllocationSeeder::PLAN as $code => $models) {
            $dealer = DevelopmentDemoFixtures::require(Dealer::class, ['code' => $code]);
            foreach ($models as $name => $quantity) {
                $vehicle = DevelopmentVehicleSeeder::vehicle($name);
                $allocation = DevelopmentDemoFixtures::require(DealerVehicleAllocation::class, [
                    'dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'status' => 'active',
                ]);
                foreach ($dates as $date) {
                    foreach ($templates as [$start, $end]) {
                        DevelopmentDemoFixtures::ensure(TestDriveSlot::class, [
                            'dealer_vehicle_allocation_id' => $allocation->id,
                            'slot_date' => $date->toDateString(), 'start_time' => $start.':00', 'end_time' => $end.':00',
                        ], ['vehicle_id' => $vehicle->id]);
                    }
                }
            }
        }
    }
}
