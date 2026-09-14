<?php

namespace Database\Seeders\ClientDemo;

use Database\Seeders\DevelopmentDemoFixtures;

use App\Models\Customer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Services\BusinessCalendar;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentTestDriveSeeder extends Seeder
{
    public const SALESMAN_EMAIL = 'demo.salesman.testdrive@example.test';
    public const CUSTOMER_EMAIL = 'demo.rahim.testdrive@example.test';
    public const NOTES = [
        '[DEMO-F32] Initial Outlander Test Drive',
        '[DEMO-F33] Completed Test Drive ready to book',
        '[DEMO-PRESENTATION] Upcoming Xforce Test Drive',
        '[DEMO-PRESENTATION] Follow-up Pajero Sport Test Drive',
    ];

    public function run(): void
    {
        DevelopmentDemoFixtures::guard();
        $dates = DevelopmentTestDriveSlotSeeder::demoDates();
        $templates = app(BusinessCalendar::class)->slotTemplates();
        if ($templates === []) {
            throw new LogicException('The business calendar has no Test Drive slot templates.');
        }
        [$start, $end] = $templates[0];
        $scenarios = [
            [self::CUSTOMER_EMAIL, 'Outlander', 'completed', 'booking'],
            [self::CUSTOMER_EMAIL, 'Outlander', 'completed', null],
            ['demo.nusrat@example.test', 'Xforce', 'scheduled', null],
            ['demo.nusrat@example.test', 'Pajero Sport', 'completed', 'follow_up'],
        ];
        foreach ($scenarios as $index => [$email, $model, $status, $outcome]) {
            $customer = DevelopmentDemoFixtures::require(Customer::class, ['email' => $email]);
            $vehicle = DevelopmentVehicleSeeder::vehicle($model);
            $allocation = DevelopmentDemoFixtures::require(DealerVehicleAllocation::class, [
                'dealer_id' => $customer->salesman->dealer_id, 'vehicle_id' => $vehicle->id, 'status' => 'active',
            ]);
            $slot = DevelopmentDemoFixtures::require(TestDriveSlot::class, [
                'dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => $dates[$index]->toDateString(),
                'start_time' => $start.':00', 'end_time' => $end.':00',
            ]);
            $occupant = TestDrive::query()->where('slot_id', $slot->id)->first();
            if ($occupant && $occupant->notes !== self::NOTES[$index]) {
                throw new LogicException('A demo slot is already occupied; no reservation was changed.');
            }
            DevelopmentDemoFixtures::ensure(TestDrive::class, ['notes' => self::NOTES[$index]], [
                'customer_id' => $customer->id, 'salesman_id' => $customer->salesman_id,
                'vehicle_id' => $vehicle->id, 'slot_id' => $slot->id,
                'scheduled_date' => $dates[$index]->toDateString(), 'scheduled_time' => $start.':00',
                'status' => $status, 'outcome' => $outcome,
                'decided_at' => $outcome ? $dates[$index]->copy()->setTimeFromTimeString($end)->toDateTimeString() : null,
                'follow_up_date' => $outcome === 'follow_up' ? $dates[2]->toDateString() : null,
                'outcome_notes' => $outcome === 'follow_up' ? 'Demo: discuss financing options.' : null,
            ]);
        }
    }
}
