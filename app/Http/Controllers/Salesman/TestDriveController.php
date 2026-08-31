<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\TestDriveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\StoreTestDriveRequest;
use App\Http\Requests\Salesman\StoreCalendarTestDriveRequest;
use App\Http\Requests\Salesman\UpdateTestDriveRequest;
use App\Models\Customer;
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

class TestDriveController extends Controller
{
    public function calendar(Request $request): View
    {
        try {
            $weekStart = Carbon::parse($request->string('week')->toString() ?: today()->toDateString())->startOfWeek();
        } catch (\Throwable) {
            $weekStart = today()->startOfWeek();
        }
        $weekEnd = $weekStart->copy()->endOfWeek();
        $allocations = $this->dealerVehicles($request)
            ->with(['demoAllocations' => fn ($query) => $query
                ->where('dealer_id', $request->user()->dealer_id)
                ->where('status', 'active')])
            ->orderBy('name')->orderBy('variant')->get();

        $vehicleId = $request->integer('vehicle');
        if ($vehicleId && ! $allocations->contains('id', $vehicleId)) {
            abort(404);
        }
        $status = $request->string('status')->toString();
        if (! in_array($status, ['', 'available', 'booked'], true)) {
            abort(404);
        }

        $slots = $this->dealerSlots($request, false)
            ->whereBetween('slot_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
            ->when($status === 'available', fn (Builder $query) => $query->whereDoesntHave('testDrive'))
            ->when($status === 'booked', fn (Builder $query) => $query->whereHas('testDrive'))
            ->with(['testDrive.customer'])
            ->get();

        return view('pages.salesman.calendar', [
            'allocations' => $allocations,
            'slots' => $slots,
            'customers' => $this->ownedCustomers($request)->orderBy('name')->get(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => collect(range(0, 6))->map(fn (int $day) => $weekStart->copy()->addDays($day)),
            'vehicleId' => $vehicleId,
            'statusFilter' => $status,
            'availableCount' => $slots->where('is_booked', false)->count(),
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

    public function create(Request $request, string $customer): View
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);

        return view('pages.salesman.test-drives.create', [
            'customer' => $customer,
            'vehicles' => $this->dealerVehicles($request)->orderBy('name')->orderBy('variant')->get(),
            'slots' => $this->dealerSlots($request)->get(),
        ]);
    }

    public function store(StoreTestDriveRequest $request, string $customer): RedirectResponse
    {
        $customer = $this->ownedCustomers($request)->findOrFail($customer);
        $testDrive = $this->schedule($request, $customer, (int) $request->validated('slot_id'), $request->validated('notes'));

        return redirect()->route('salesman.test-drives.show', $testDrive)
            ->with('success', 'Test Drive scheduled successfully.');
    }

    public function storeFromCalendar(StoreCalendarTestDriveRequest $request): RedirectResponse|JsonResponse
    {
        $customer = $this->ownedCustomers($request)->findOrFail($request->validated('customer_id'));
        $testDrive = $this->schedule($request, $customer, (int) $request->validated('slot_id'), $request->validated('notes'));
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
        DB::transaction(function () use ($request, $testDrive): void {
            $slot = $this->dealerSlots($request)->lockForUpdate()->findOrFail($request->validated('slot_id'));
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

    private function schedule(Request $request, Customer $customer, int $slotId, ?string $notes): TestDrive
    {
        return DB::transaction(function () use ($request, $customer, $slotId, $notes): TestDrive {
            $slot = $this->dealerSlots($request)->lockForUpdate()->findOrFail($slotId);
            if ($slot->testDrive()->exists()) {
                throw ValidationException::withMessages(['slot_id' => 'This Test Drive Slot has already been booked.']);
            }

            return TestDrive::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $slot->vehicle_id,
                'salesman_id' => $request->user()->id,
                'slot_id' => $slot->id,
                'scheduled_date' => $slot->slot_date,
                'scheduled_time' => $slot->start_time,
                'notes' => $notes,
                'status' => TestDriveStatus::Scheduled,
            ]);
        });
    }
}
