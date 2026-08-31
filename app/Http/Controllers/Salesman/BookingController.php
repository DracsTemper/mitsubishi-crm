<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\BookingStatus;
use App\Enums\TestDriveStatus;
use App\Enums\TestDriveOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\TestDrive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.salesman.bookings', [
            'bookings' => $this->ownedBookings($request)
                ->with(['customer', 'vehicle', 'testDrive', 'salesman.dealer'])
                ->latest('booking_date')->latest('id')->paginate(10),
        ]);
    }

    public function create(Request $request, string $customer, string $testDrive): View
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);
        $testDrive = $this->eligibleTestDrive($request, $customer, $testDrive);

        return view('pages.salesman.booking-create', compact('customer', 'testDrive'));
    }

    public function store(StoreBookingRequest $request, string $customer, string $testDrive): RedirectResponse
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);
        $testDrive = $this->eligibleTestDrive($request, $customer, $testDrive);
        $bookingAmount = (float) $request->validated('booking_amount');
        if ($bookingAmount > (float) $testDrive->vehicle->price) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'booking_amount' => 'The Booking amount cannot exceed the Vehicle price.',
            ]);
        }
        $booking = DB::transaction(function () use ($request, $testDrive): Booking {
            $booking = Booking::query()->create([
                ...$request->safe()->only(['booking_date', 'expected_delivery_date', 'booking_amount', 'notes']),
                'test_drive_id' => $testDrive->id,
                'customer_id' => $testDrive->customer_id,
                'vehicle_id' => $testDrive->vehicle_id,
                'salesman_id' => $request->user()->id,
                'status' => BookingStatus::Pending,
            ]);
            if ($testDrive->outcome === null) {
                $testDrive->update(['outcome' => TestDriveOutcome::Booking, 'decided_at' => now()]);
            }

            return $booking;
        });

        return redirect()->route('salesman.bookings.show', $booking)
            ->with('success', 'Vehicle model booked successfully.');
    }

    public function show(Request $request, string $booking): View
    {
        $booking = $this->ownedBookings($request)
            ->with(['customer', 'vehicle', 'testDrive', 'salesman.dealer'])
            ->findOrFail($booking);

        return view('pages.salesman.booking-show', compact('booking'));
    }

    private function eligibleTestDrive(Request $request, Customer $customer, string $testDrive): TestDrive
    {
        return TestDrive::query()
            ->whereKey($testDrive)
            ->where('salesman_id', $request->user()->id)
            ->where('customer_id', $customer->id)
            ->where('status', TestDriveStatus::Completed->value)
            ->where(fn (Builder $query) => $query->whereNull('outcome')->orWhere('outcome', TestDriveOutcome::Booking->value))
            ->whereDoesntHave('booking')
            ->where(function (Builder $query) use ($request): void {
                $scope = fn (Builder $allocation) => $allocation
                    ->where('dealer_id', $request->user()->dealer_id)
                    ->where('status', 'active');
                $query->whereHas('slot.demoAllocation', $scope)
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('slot_id')->whereHas('vehicle.demoAllocations', $scope));
            })
            ->with(['vehicle', 'salesman.dealer'])
            ->firstOrFail();
    }

    private function ownedCustomers(Request $request): Builder
    {
        return Customer::query()->where('salesman_id', $request->user()->id);
    }

    private function ownedBookings(Request $request): Builder
    {
        return Booking::query()
            ->where('salesman_id', $request->user()->id)
            ->whereHas('customer', fn (Builder $query) => $query->where('salesman_id', $request->user()->id));
    }
}
