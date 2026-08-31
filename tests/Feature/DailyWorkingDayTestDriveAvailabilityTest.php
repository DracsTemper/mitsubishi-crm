<?php

namespace Tests\Feature;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BusinessCalendar;
use App\Services\TestDriveAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DailyWorkingDayTestDriveAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 08:00:00'); // Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_saturday_through_thursday_have_five_virtual_slots_without_database_growth(): void
    {
        [$dealer, , , $allocation] = $this->context();
        $service = app(TestDriveAvailability::class);
        $days = collect(range(0, 6))->map(fn (int $offset) => Carbon::parse('2026-08-31')->addDays($offset));

        $slots = $service->forRange(collect([$allocation->load('vehicle')]), $days);

        foreach (['2026-08-31', '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-05', '2026-09-06'] as $workingDay) {
            $this->assertSame(5, $slots->filter(fn ($slot) => $slot->slot_date->toDateString() === $workingDay)->count());
        }
        $this->assertSame(0, $slots->filter(fn ($slot) => $slot->slot_date->toDateString() === '2026-09-04')->count());
        $this->assertDatabaseCount('test_drive_slots', 0);
        $this->assertSame($dealer->id, $allocation->dealer_id);
    }

    public function test_calendar_shows_daily_slots_and_a_clear_friday_closed_state(): void
    {
        [, $salesman] = $this->context();

        $response = $this->actingAs($salesman)->get(route('salesman.calendar', ['week' => '2026-08-31']));

        $response->assertOk()->assertSee('30')->assertSee('OUTLET CLOSED')->assertSee('Weekly Holiday')->assertSee('No Test Drives Available');
        $this->assertDatabaseCount('test_drive_slots', 0);
    }

    public function test_working_day_booking_lazily_materializes_one_slot_and_duplicate_is_rejected(): void
    {
        [, $salesman, $customer, $allocation] = $this->context();
        $payload = ['customer_id' => $customer->id, 'allocation_id' => $allocation->id, 'slot_date' => '2026-09-01', 'start_time' => '10:00'];

        $this->actingAs($salesman)->post(route('salesman.calendar.test-drives.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('test_drive_slots', 1);
        $testDrive = TestDrive::firstOrFail();
        $this->assertSame([$customer->id, $salesman->id, '2026-09-01', '10:00'], [$testDrive->customer_id, $testDrive->salesman_id, $testDrive->scheduled_date->toDateString(), substr($testDrive->scheduled_time, 0, 5)]);

        $this->post(route('salesman.calendar.test-drives.store'), $payload)->assertSessionHasErrors('slot_choice');
        $this->assertDatabaseCount('test_drive_slots', 1);
        $this->assertDatabaseCount('test_drives', 1);
    }

    public function test_friday_holiday_past_and_non_template_booking_attempts_are_rejected_server_side(): void
    {
        [, $salesman, $customer, $allocation] = $this->context();
        Config::set('business-calendar.holidays.2026-09-02', 'Demonstration Government Holiday');
        $base = ['customer_id' => $customer->id, 'allocation_id' => $allocation->id];

        $this->actingAs($salesman)->post(route('salesman.calendar.test-drives.store'), $base + ['slot_date' => '2026-09-04', 'start_time' => '10:00'])->assertSessionHasErrors('slot_choice');
        $this->post(route('salesman.calendar.test-drives.store'), $base + ['slot_date' => '2026-09-02', 'start_time' => '10:00'])->assertSessionHasErrors('slot_choice');
        $this->post(route('salesman.calendar.test-drives.store'), $base + ['slot_date' => '2026-08-30', 'start_time' => '10:00'])->assertSessionHasErrors('slot_choice');
        $this->post(route('salesman.calendar.test-drives.store'), $base + ['slot_date' => '2026-09-01', 'start_time' => '13:15'])->assertSessionHasErrors('slot_choice');
        $this->assertDatabaseCount('test_drive_slots', 0);
        $this->assertDatabaseCount('test_drives', 0);
    }

    public function test_configured_holiday_is_visible_in_calendar_and_schedule_page(): void
    {
        [, $salesman, $customer] = $this->context();
        Config::set('business-calendar.holidays.2026-09-02', 'Victory Day Demonstration');

        $this->actingAs($salesman)->get(route('salesman.calendar', ['week' => '2026-08-31']))
            ->assertOk()->assertSee('Holiday: Victory Day Demonstration')->assertSee('OUTLET CLOSED');
        $this->get(route('salesman.customers.test-drives.create', ['customer' => $customer, 'date' => '2026-09-02']))
            ->assertOk()->assertSee('Holiday: Victory Day Demonstration')->assertSee('No Test Drives Available');
    }

    public function test_existing_booking_marks_only_its_vehicle_dealer_date_and_time_as_booked(): void
    {
        [$dealer, $salesman, $customer, $allocation] = $this->context();
        $secondVehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Independent Xforce']);
        $secondAllocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $secondVehicle->id, 'quantity' => 1, 'status' => 'active']);
        $slot = TestDriveSlot::create(['vehicle_id' => $allocation->vehicle_id, 'dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => '2026-09-01', 'start_time' => '11:00', 'end_time' => '11:30']);
        TestDrive::create(['customer_id' => $customer->id, 'vehicle_id' => $allocation->vehicle_id, 'salesman_id' => $salesman->id, 'slot_id' => $slot->id, 'scheduled_date' => '2026-09-01', 'scheduled_time' => '11:00', 'status' => TestDriveStatus::Scheduled]);

        $slots = app(TestDriveAvailability::class)->forRange(collect([$allocation->load('vehicle'), $secondAllocation->load('vehicle')]), collect([Carbon::parse('2026-09-01')]));
        $this->assertTrue($slots->first(fn ($item) => $item->dealer_vehicle_allocation_id === $allocation->id && $item->start_time === '11:00')->is_booked);
        $this->assertFalse($slots->first(fn ($item) => $item->dealer_vehicle_allocation_id === $secondAllocation->id && $item->start_time === '11:00')->is_booked);
    }

    public function test_cross_dealer_allocation_and_cross_salesman_customer_are_rejected(): void
    {
        [, $salesman, $customer] = $this->context();
        [$otherDealer, $otherSalesman, $otherCustomer, $otherAllocation] = $this->context('Other Dealer');
        $payload = ['allocation_id' => $otherAllocation->id, 'slot_date' => '2026-09-01', 'start_time' => '10:00'];

        $this->actingAs($salesman)->post(route('salesman.calendar.test-drives.store'), $payload + ['customer_id' => $customer->id])->assertNotFound();
        $this->post(route('salesman.calendar.test-drives.store'), ['allocation_id' => $this->allocationFor($salesman)->id, 'slot_date' => '2026-09-01', 'start_time' => '10:00', 'customer_id' => $otherCustomer->id])->assertNotFound();
        $this->assertDatabaseCount('test_drives', 0);
        $this->assertNotSame($salesman->dealer_id, $otherDealer->id);
        $this->assertNotSame($salesman->id, $otherSalesman->id);
    }

    private function context(string $dealerName = 'Mitsubishi Working Day Dealer'): array
    {
        $dealer = Dealer::factory()->create(['name' => $dealerName]);
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Working Day Outlander']);
        $allocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
        return [$dealer, $salesman, $customer, $allocation];
    }

    private function allocationFor(User $salesman): DealerVehicleAllocation
    {
        return DealerVehicleAllocation::query()->where('dealer_id', $salesman->dealer_id)->firstOrFail();
    }
}
