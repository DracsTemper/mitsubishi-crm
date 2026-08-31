<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\DealerVehicleAllocation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.dealer.inventory', [
            'allocations' => DealerVehicleAllocation::query()
                ->where('dealer_id', (int) $request->user()->dealer_id)
                ->where('status', 'active')
                ->with(['dealer', 'vehicle.testDriveSlots'])
                ->whereHas('vehicle')->orderBy('vehicle_id')->get()->sortBy('vehicle.name'),
            'statuses' => DealerVehicleAllocation::STATUSES,
        ]);
    }
}
