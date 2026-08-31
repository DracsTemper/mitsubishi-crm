<?php

namespace Tests\Feature;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDriveSlotAndBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_list_shows_available_and_disables_booked_slots_without_free_time_input(): void
    {
        [, $salesman, $customer, $vehicle] = $this->context();
        $available = $this->slot($vehicle, '10:00', '10:30');
        $booked = $this->slot($vehicle, '11:00', '11:30');
        $otherCustomer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $this->testDrive($otherCustomer, $vehicle, $salesman, $booked);

        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $customer))
            ->assertOk()->assertSee('10:00 AM')->assertSee('AVAILABLE')->assertSee('BOOKED')
            ->assertSee('value="'.$available->id.'"', false)
            ->assertSee('value="'.$booked->id.'"', false)->assertSee('is-booked', false)
            ->assertDontSee('name="scheduled_time"', false);
    }

    public function test_selected_slot_drives_vehicle_date_and_time_and_cannot_be_double_booked(): void
    {
        [, $salesman, $customer, $vehicle] = $this->context();
        $slot = $this->slot($vehicle, '15:00', '15:30');
        $route = route('salesman.customers.test-drives.store', $customer);
        $this->actingAs($salesman)->post($route, ['slot_id' => $slot->id, 'notes' => 'First'])->assertRedirect();
        $testDrive = TestDrive::query()->firstOrFail();
        $this->assertSame($slot->id, $testDrive->slot_id);
        $this->assertSame($vehicle->id, $testDrive->vehicle_id);
        $this->assertSame('2026-09-02', $testDrive->scheduled_date->format('Y-m-d'));
        $this->assertStringStartsWith('15:00', $testDrive->scheduled_time);

        $secondCustomer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $secondCustomer), ['slot_id' => $slot->id])->assertSessionHasErrors('slot_id');
        $this->assertDatabaseCount('test_drives', 1);
    }

    public function test_slot_tampering_cannot_cross_dealer_or_customer_ownership(): void
    {
        [, $salesman, $customer] = $this->context();
        [, , $otherCustomer, $otherVehicle] = $this->context();
        $otherSlot = $this->slot($otherVehicle, '12:00', '12:30');
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $otherSlot->id])->assertNotFound();
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $otherCustomer), ['slot_id' => $otherSlot->id, 'salesman_id' => $salesman->id, 'dealer_id' => $salesman->dealer_id, 'role' => 'admin'])->assertNotFound();
        $this->assertDatabaseCount('test_drives', 0);
    }

    public function test_roles_and_unassigned_salesman_remain_denied_slot_booking(): void
    {
        [, , $customer, $vehicle] = $this->context();
        $slot = $this->slot($vehicle, '12:00', '12:30');
        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slot->id])->assertForbidden();
        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slot->id])->assertForbidden();
        }
    }

    public function test_booking_amount_is_required_and_cannot_exceed_vehicle_price(): void
    {
        [, $salesman, $customer, $vehicle] = $this->context();
        $testDrive = $this->testDrive($customer, $vehicle, $salesman, $this->slot($vehicle), TestDriveStatus::Completed);
        $route = route('salesman.customers.test-drives.book.store', [$customer, $testDrive]);
        $this->actingAs($salesman)->post($route, $this->bookingPayload(['booking_amount' => null]))->assertSessionHasErrors('booking_amount');
        $this->actingAs($salesman)->post($route, $this->bookingPayload(['booking_amount' => 5000001]))->assertSessionHasErrors('booking_amount');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_due_amount_is_calculated_server_side_and_forged_money_is_ignored(): void
    {
        [, $salesman, $customer, $vehicle] = $this->context();
        $testDrive = $this->testDrive($customer, $vehicle, $salesman, $this->slot($vehicle), TestDriveStatus::Completed);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->bookingPayload([
            'booking_amount' => 500000, 'vehicle_price' => 1, 'total_paid' => 9999999, 'due_amount' => 0,
            'vehicle_id' => 999, 'customer_id' => 999, 'salesman_id' => 999, 'dealer_id' => 999,
        ]))->assertRedirect();
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('500000.00', $booking->booking_amount);
        $this->assertSame('500000.00', $booking->total_paid);
        $this->assertSame('4500000.00', $booking->due_amount);
        $this->assertSame($vehicle->id, $booking->vehicle_id);
    }

    public function test_demo_slot_and_payment_seeders_are_idempotent_and_preserve_vehicle_data(): void
    {
        $dealer = Dealer::factory()->create();
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Outlander', 'price' => 5000000, 'image' => 'assets/images/vehicles/outlander-2026.png']);
        foreach (['Xforce', 'Triton', 'Pajero Sport'] as $name) {
            Vehicle::factory()->create(['dealer_id' => Dealer::factory()->create()->id, 'name' => $name, 'price' => 5000000]);
        }
        $this->seed(DevelopmentBookingSeeder::class);
        $this->seed(DevelopmentBookingSeeder::class);
        $this->assertSame(10 * \App\Models\DealerVehicleAllocation::query()->where('status', 'active')->count(), TestDriveSlot::count());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(2, TestDriveSlot::query()->whereHas('testDrive')->count());
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('500000.00', $booking->total_paid);
        $this->assertSame('4500000.00', $booking->due_amount);
        $this->assertSame('assets/images/vehicles/outlander-2026.png', $vehicle->fresh()->image);
    }

    private function context(): array
    {
        $dealer = Dealer::factory()->create();
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'price' => 5000000, 'status' => 'available']);
        return [$dealer, $salesman, $customer, $vehicle];
    }

    private function slot(Vehicle $vehicle, string $start = '10:00', string $end = '10:30'): TestDriveSlot
    {
        return TestDriveSlot::query()->create(['vehicle_id' => $vehicle->id, 'slot_date' => '2026-09-02', 'start_time' => $start, 'end_time' => $end]);
    }

    private function testDrive(Customer $customer, Vehicle $vehicle, User $salesman, TestDriveSlot $slot, TestDriveStatus $status = TestDriveStatus::Scheduled): TestDrive
    {
        return TestDrive::query()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'salesman_id' => $salesman->id, 'slot_id' => $slot->id, 'scheduled_date' => $slot->slot_date, 'scheduled_time' => $slot->start_time, 'status' => $status]);
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge(['booking_date' => '2026-09-02', 'expected_delivery_date' => '2026-09-20', 'booking_amount' => 500000, 'notes' => 'Advance received.'], $overrides);
    }
}
