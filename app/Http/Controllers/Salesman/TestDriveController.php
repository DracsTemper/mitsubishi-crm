<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\TestDriveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\StoreTestDriveRequest;
use App\Http\Requests\Salesman\StoreCalendarTestDriveRequest;
use App\Http\Requests\Salesman\UpdateTestDriveRequest;
use App\Models\Customer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use App\Services\BusinessCalendar;
use App\Services\TestDriveAvailability;
use App\Enums\TestDriveOutcome;
use App\Enums\TestDriveLossReason;

class TestDriveController extends Controller
{
    public function calendar(Request $request, BusinessCalendar $businessCalendar, TestDriveAvailability $availability): View
    {
        try {
            $weekStart = Carbon::parse($request->string('week')->toString() ?: today()->toDateString())->startOfWeek();
        } catch (\Throwable) {
            $weekStart = today()->startOfWeek();
        }
        $weekEnd = $weekStart->copy()->endOfWeek();
        $allocations = $this->dealerAllocations($request)->with('vehicle')->get()
            ->sortBy(fn (DealerVehicleAllocation $allocation) => $allocation->vehicle->name.' '.$allocation->vehicle->variant)->values();

        $vehicleId = $request->integer('vehicle');
        if ($vehicleId && ! $allocations->contains('vehicle_id', $vehicleId)) {
            abort(404);
        }
        $status = $request->string('status')->toString();
        if (! in_array($status, ['', 'available', 'booked'], true)) {
            abort(404);
        }

        $days = collect(range(0, 6))->map(fn (int $day) => $weekStart->copy()->addDays($day));
        $visibleAllocations = $allocations->when($vehicleId, fn ($items) => $items->where('vehicle_id', $vehicleId));
        $slots = $availability->forRange($visibleAllocations, $days)
            ->when($status === 'available', fn ($items) => $items->where('is_booked', false)->where('is_bookable', true))
            ->when($status === 'booked', fn ($items) => $items->where('is_booked', true))->values();

        return view('pages.salesman.calendar', [
            'allocations' => $allocations,
            'slots' => $slots,
            'customers' => $this->ownedCustomers($request)->orderBy('name')->get(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'closureReasons' => $days->mapWithKeys(fn (Carbon $day) => [$day->toDateString() => $businessCalendar->closureReason($day)]),
            'vehicleId' => $vehicleId,
            'statusFilter' => $status,
            'availableCount' => $slots->where('is_booked', false)->where('is_bookable', true)->count(),
            'bookedCount' => $slots->where('is_booked', true)->count(),
        ]);
    }

    public function index(Request $request): View
    {
        $testDrives = $this->ownedTestDrives($request)
            ->with(['customer', 'vehicle', 'salesman.dealer', 'booking'])
            ->orderByRaw('scheduled_date >= ? desc', [now()->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->paginate(10);

        return view('pages.salesman.test-drives.index', compact('testDrives'));
    }

    public function create(Request $request, string $customer, BusinessCalendar $businessCalendar, TestDriveAvailability $availability): View
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);
        try {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $request->string('date')->toString() ?: today()->toDateString())->startOfDay();
        } catch (\Throwable) {
            $selectedDate = today();
        }
        $allocations = $this->dealerAllocations($request)->with('vehicle')->get()
            ->sortBy(fn (DealerVehicleAllocation $allocation) => $allocation->vehicle->name)->values();

        return view('pages.salesman.test-drives.create', [
            'customer' => $customer,
            'vehicles' => $allocations->pluck('vehicle'),
            'slots' => $availability->forRange($allocations, collect([$selectedDate])),
            'selectedDate' => $selectedDate,
            'closureReason' => $businessCalendar->closureReason($selectedDate),
        ]);
    }

    public function store(StoreTestDriveRequest $request, string $customer): RedirectResponse
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);
        $testDrive = $this->schedule($request, $customer, $request->validated());

        return redirect()->route('salesman.test-drives.show', $testDrive)
            ->with('success', 'Test Drive scheduled successfully.');
    }

    public function storeFromCalendar(StoreCalendarTestDriveRequest $request): RedirectResponse|JsonResponse
    {
        $customer = $this->ownedCustomers($request)->findOrFail($request->validated('customer_id'));
        $testDrive = $this->schedule($request, $customer, $request->validated());
        $week = $testDrive->scheduled_date->copy()->startOfWeek()->toDateString();
        $refreshUrl = route('salesman.calendar', ['week' => $week]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Test Drive booked for '.$customer->name.'.',
                'data' => ['refresh_url' => $refreshUrl],
            ]);
        }

        return redirect($refreshUrl)->with('success', 'Test Drive booked for '.$customer->name.'.');
    }

    public function show(Request $request, string $testDrive): View
    {
        $testDrive = $this->ownedTestDrives($request)
            ->with(['customer', 'vehicle', 'salesman.dealer', 'booking'])
            ->findOrFail($testDrive);

        return view('pages.salesman.test-drives.show', [
            'testDrive' => $testDrive,
            'statuses' => TestDriveStatus::cases(),
            'outcomes' => TestDriveOutcome::cases(),
            'lossReasons' => TestDriveLossReason::cases(),
        ]);
    }

    public function edit(Request $request, string $testDrive): View
    {
        $testDrive = $this->ownedTestDrives($request)
            ->with(['customer', 'vehicle', 'salesman.dealer'])
            ->findOrFail($testDrive);

        return view('pages.salesman.test-drives.edit', [
            'testDrive' => $testDrive,
            'statuses' => TestDriveStatus::cases(),
            'slots' => $this->dealerSlots($request)->get(),
        ]);
    }

    public function update(UpdateTestDriveRequest $request, string $testDrive): RedirectResponse|JsonResponse
    {
        $testDrive = $this->ownedTestDrives($request)->findOrFail($testDrive);
        if ($testDrive->outcome !== null && $request->validated('status') !== TestDriveStatus::Completed->value) {
            throw ValidationException::withMessages(['status' => 'A Test Drive with a recorded Customer decision must remain completed.']);
        }
        DB::transaction(function () use ($request, $testDrive): void {
            $slot = $this->dealerSlots($request)->lockForUpdate()->findOrFail($request->validated('slot_id'));
            $this->assertWorkingSlot($slot->slot_date, $slot->start_time, 'slot_id');
            if ($slot->vehicle_id !== $testDrive->vehicle_id) {
                throw ValidationException::withMessages(['slot_id' => 'Rescheduling must use a Slot for the same Vehicle model.']);
            }
            if ($slot->testDrive()->whereKeyNot($testDrive->id)->exists()) {
                throw ValidationException::withMessages(['slot_id' => 'This Test Drive Slot has already been booked.']);
            }
            $testDrive->update([
                'slot_id' => $slot->id, 'vehicle_id' => $slot->vehicle_id,
                'scheduled_date' => $slot->slot_date, 'scheduled_time' => $slot->start_time,
                'status' => $request->validated('status'), 'notes' => $request->validated('notes'),
            ]);
        });

        $message = $testDrive->status === TestDriveStatus::Rescheduled
            ? 'Test Drive rescheduled successfully.'
            : 'Test Drive updated successfully.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['refresh_url' => route('salesman.test-drives.show', $testDrive)],
            ]);
        }

        return redirect()->route('salesman.test-drives.show', $testDrive)->with('success', $message);
    }

    /** @return Builder<TestDrive> */
    private function ownedTestDrives(Request $request): Builder
    {
        return TestDrive::query()
            ->where('salesman_id', $request->user()->id)
            ->whereHas('customer', fn (Builder $query) => $query->where('salesman_id', $request->user()->id));
    }

    /** @return Builder<Customer> */
    private function ownedCustomers(Request $request): Builder
    {
        return Customer::query()->where('salesman_id', $request->user()->id);
    }

    /** @return Builder<Vehicle> */
    private function dealerVehicles(Request $request): Builder
    {
        return Vehicle::query()->whereHas('demoAllocations', fn (Builder $query) => $query
            ->where('dealer_id', $request->user()->dealer_id)
            ->where('status', 'active'));
    }

    /** @return Builder<DealerVehicleAllocation> */
    private function dealerAllocations(Request $request): Builder
    {
        return DealerVehicleAllocation::query()
            ->where('dealer_id', $request->user()->dealer_id)
            ->where('status', 'active');
    }

    private function dealerSlots(Request $request, bool $futureOnly = true): Builder
    {
        return TestDriveSlot::query()
            ->when($futureOnly, fn (Builder $query) => $query->whereDate('slot_date', '>=', today()))
            ->whereHas('demoAllocation', fn (Builder $query) => $query
                ->where('dealer_id', $request->user()->dealer_id)
                ->where('status', 'active'))
            ->with(['vehicle', 'demoAllocation', 'testDrive'])
            ->orderBy('slot_date')->orderBy('start_time');
    }

    /** @param array<string, mixed> $data */
    private function schedule(Request $request, Customer $customer, array $data): TestDrive
    {
        return DB::transaction(function () use ($request, $customer, $data): TestDrive {
            if (! empty($data['slot_id'])) {
                $slot = $this->dealerSlots($request)->lockForUpdate()->findOrFail($data['slot_id']);
                $this->assertWorkingSlot($slot->slot_date, $slot->start_time, 'slot_id');
            } else {
                $allocation = $this->dealerAllocations($request)->lockForUpdate()->findOrFail($data['allocation_id']);
                $date = Carbon::createFromFormat('Y-m-d', $data['slot_date'])->startOfDay();
                $endTime = $this->assertWorkingSlot($date, $data['start_time'], 'slot_choice');
                $slot = TestDriveSlot::query()->firstOrCreate(
                    [
                        'dealer_vehicle_allocation_id' => $allocation->id,
                        'slot_date' => $date,
                        'start_time' => $data['start_time'],
                        'end_time' => $endTime,
                    ],
                    ['vehicle_id' => $allocation->vehicle_id],
                );
                $slot = TestDriveSlot::query()->lockForUpdate()->findOrFail($slot->id);
            }
            if ($slot->testDrive()->exists()) {
                throw ValidationException::withMessages([! empty($data['slot_id']) ? 'slot_id' : 'slot_choice' => 'This Test Drive Slot has already been booked.']);
            }

            return TestDrive::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $slot->vehicle_id,
                'salesman_id' => $request->user()->id,
                'slot_id' => $slot->id,
                'scheduled_date' => $slot->slot_date,
                'scheduled_time' => $slot->start_time,
                'notes' => $data['notes'] ?? null,
                'status' => TestDriveStatus::Scheduled,
            ]);
        });
    }

    private function assertWorkingSlot(Carbon|string $date, string $startTime, string $field): string
    {
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        if ($date->isBefore(today())) {
            throw ValidationException::withMessages([$field => 'Test Drives cannot be scheduled in the past.']);
        }
        $businessCalendar = app(BusinessCalendar::class);
        if ($reason = $businessCalendar->closureReason($date)) {
            throw ValidationException::withMessages([$field => 'The outlet is closed on this date. '.$reason.'.']);
        }
        $endTime = $businessCalendar->endTimeFor($startTime);
        if (! $endTime) {
            throw ValidationException::withMessages([$field => 'The selected time is not a configured Test Drive Slot.']);
        }

        return $endTime;
    }
}
