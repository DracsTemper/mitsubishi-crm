<?php

namespace Database\Seeders;

use App\Models\TestDriveSlot;
use App\Models\DealerVehicleAllocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use LogicException;

class DevelopmentTestDriveSlotSeeder extends Seeder
{
    /** @return list<Carbon> */
    public static function demoDates(): array
    {
        return [today()->addDays(2), today()->addDays(5)];
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development Test Drive Slots may only be seeded locally or in tests.');
        }
        $allocations = DealerVehicleAllocation::query()
            ->where('status', 'active')
            ->with('vehicle')
            ->orderBy('dealer_id')->orderBy('vehicle_id')
            ->get();
        if ($allocations->isEmpty()) {
            throw new LogicException('Active Dealer Vehicle allocations must exist before Slots are seeded.');
        }
        foreach ($allocations as $allocation) {
            $vehicle = $allocation->vehicle;
            foreach (self::demoDates() as $date) {
                $slotDate = $date->copy()->startOfDay();
                foreach (config('business-calendar.test_drive_slots') as [$start, $end]) {
                    TestDriveSlot::query()->updateOrCreate(
                        ['dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => $slotDate, 'start_time' => $start, 'end_time' => $end],
                        ['vehicle_id' => $vehicle->id],
                    );
                }
            }
        }
    }
}
