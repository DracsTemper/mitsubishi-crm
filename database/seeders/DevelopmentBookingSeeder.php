<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\TestDriveStatus;
use App\Models\Booking;
use App\Models\TestDrive;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentBookingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Booking fixtures may only be seeded locally or in tests.');
        }

        $this->call(DevelopmentTestDriveSeeder::class);
        $this->call(DevelopmentDemoVehicleAvailabilitySeeder::class);
        $this->call(DevelopmentTestDriveSlotSeeder::class);
        [$primaryDate, $secondaryDate] = DevelopmentTestDriveSlotSeeder::demoDates();
        $testDrive = TestDrive::query()
            ->whereHas('customer', fn ($query) => $query->where('email', DevelopmentTestDriveSeeder::CUSTOMER_EMAIL))
            ->where('notes', '[DEMO-F32] Initial Outlander Test Drive')
            ->firstOrFail();
        $testDrive->update(['status' => TestDriveStatus::Completed]);
        $initialSlot = \App\Models\TestDriveSlot::query()->where('vehicle_id', $testDrive->vehicle_id)->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $testDrive->salesman->dealer_id))->whereDate('slot_date', $primaryDate)->where('start_time', '15:00')->firstOrFail();
        $testDrive->update(['slot_id' => $initialSlot->id, 'scheduled_date' => $initialSlot->slot_date, 'scheduled_time' => $initialSlot->start_time]);

        Booking::query()->updateOrCreate(
            ['test_drive_id' => $testDrive->id],
            [
                'customer_id' => $testDrive->customer_id,
                'vehicle_id' => $testDrive->vehicle_id,
                'salesman_id' => $testDrive->salesman_id,
                'booking_date' => $primaryDate,
                'expected_delivery_date' => $primaryDate->copy()->addDays(18),
                'booking_amount' => 500000,
                'status' => BookingStatus::Pending,
                'notes' => '[DEMO-F33] Customer said YES after completed Test Drive.',
            ],
        );

        $candidate = TestDrive::query()->updateOrCreate(
            ['customer_id' => $testDrive->customer_id, 'notes' => '[DEMO-F33] Completed Test Drive ready to book'],
            [
                'vehicle_id' => $testDrive->vehicle_id,
                'salesman_id' => $testDrive->salesman_id,
                'scheduled_date' => $secondaryDate,
                'scheduled_time' => '15:00',
                'status' => TestDriveStatus::Completed,
            ],
        );
        $candidateSlot = \App\Models\TestDriveSlot::query()->where('vehicle_id', $candidate->vehicle_id)->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $candidate->salesman->dealer_id))->whereDate('slot_date', $secondaryDate)->where('start_time', '15:00')->firstOrFail();
        $candidate->update(['slot_id' => $candidateSlot->id, 'scheduled_date' => $candidateSlot->slot_date, 'scheduled_time' => $candidateSlot->start_time]);

        $demoVehicleIds = \App\Models\Vehicle::query()
            ->whereIn('name', DevelopmentDemoVehicleAvailabilitySeeder::VEHICLE_NAMES)
            ->pluck('id');
        \App\Models\TestDriveSlot::query()
            ->whereIn('vehicle_id', $demoVehicleIds)
            ->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $testDrive->salesman->dealer_id))
            ->whereDate('slot_date', '!=', $primaryDate)
            ->whereDate('slot_date', '!=', $secondaryDate)
            ->whereDoesntHave('testDrive')
            ->delete();
    }
}
