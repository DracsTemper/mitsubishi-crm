<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DealerVehicleAllocation;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealerVehicleAllocationController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'dealer_id' => ['required', 'integer', Rule::exists('dealers', 'id'), Rule::unique('dealer_vehicle_allocations', 'dealer_id')->where('vehicle_id', $vehicle->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in(DealerVehicleAllocation::STATUSES)],
        ]);
        DealerVehicleAllocation::query()->create(['vehicle_id' => $vehicle->id, ...$data]);
        return back()->with('success', 'Demo allocation created successfully.');
    }

    public function update(Request $request, Vehicle $vehicle, DealerVehicleAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->vehicle_id === $vehicle->id, 404);
        $allocation->update($request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000'], 'status' => ['required', Rule::in(DealerVehicleAllocation::STATUSES)]]));
        return back()->with('success', 'Demo allocation updated successfully.');
    }

    public function destroy(Vehicle $vehicle, DealerVehicleAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->vehicle_id === $vehicle->id, 404);
        if ($allocation->testDriveSlots()->whereHas('testDrive')->exists()) {
            return back()->withErrors(['allocation' => 'An allocation with booked Test Drive Slots cannot be removed.']);
        }
        $allocation->testDriveSlots()->delete();
        $allocation->delete();
        return back()->with('success', 'Demo allocation removed successfully.');
    }
}
