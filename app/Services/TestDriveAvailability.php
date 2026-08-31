<?php

namespace App\Services;

use App\Models\DealerVehicleAllocation;
use App\Models\TestDriveSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TestDriveAvailability
{
    public function __construct(private readonly BusinessCalendar $calendar) {}

    /**
     * @param Collection<int, DealerVehicleAllocation> $allocations
     * @param Collection<int, Carbon> $days
     * @return Collection<int, object>
     */
    public function forRange(Collection $allocations, Collection $days): Collection
    {
        if ($allocations->isEmpty() || $days->isEmpty()) {
            return collect();
        }

        $existing = TestDriveSlot::query()
            ->whereIn('dealer_vehicle_allocation_id', $allocations->pluck('id'))
            ->whereDate('slot_date', '>=', $days->first()->toDateString())
            ->whereDate('slot_date', '<=', $days->last()->toDateString())
            ->with(['testDrive.customer'])
            ->get()
            ->keyBy(fn (TestDriveSlot $slot) => sprintf('%d|%s|%s', $slot->dealer_vehicle_allocation_id, $slot->slot_date->toDateString(), Carbon::parse($slot->start_time)->format('H:i')));

        return $allocations->flatMap(function (DealerVehicleAllocation $allocation) use ($days, $existing): Collection {
            return $days->flatMap(function (Carbon $date) use ($allocation, $existing): Collection {
                if (! $this->calendar->isOpen($date)) {
                    return collect();
                }

                return collect($this->calendar->slotTemplates())->map(function (array $window) use ($allocation, $date, $existing): object {
                    [$start, $end] = $window;
                    $persisted = $existing->get(sprintf('%d|%s|%s', $allocation->id, $date->toDateString(), Carbon::parse($start)->format('H:i')));

                    return (object) [
                        'id' => $persisted?->id,
                        'dealer_vehicle_allocation_id' => $allocation->id,
                        'vehicle_id' => $allocation->vehicle_id,
                        'vehicle' => $allocation->vehicle,
                        'slot_date' => $date->copy(),
                        'start_time' => $start,
                        'end_time' => $end,
                        'testDrive' => $persisted?->testDrive,
                        'is_booked' => $persisted !== null && $persisted->testDrive !== null,
                        'is_bookable' => $date->isToday() || $date->isFuture(),
                    ];
                });
            });
        })->values();
    }
}
