<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanOwnershipDemoWorkflowCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_salesman_is_the_only_customer_owner_and_dealer_is_derived(): void
    {
        [$dealer, $salesman] = $this->salesmanContext('Mitsubishi Uttara');
        [, $attacker] = $this->salesmanContext('Mitsubishi Dhanmondi');

        $this->actingAs($salesman)->post(route('salesman.customers.store'), [
            ...$this->customerPayload(),
            'salesman_id' => $attacker->id,
            'dealer_id' => $attacker->dealer_id,
            'role' => 'admin',
        ])->assertRedirect();

        $customer = Customer::firstOrFail();
        $this->assertSame($salesman->id, $customer->salesman_id);
        $this->assertSame($dealer->id, $customer->salesman->dealer_id);
        $this->assertArrayNotHasKey('dealer_id', $customer->getAttributes());
        $this->actingAs($attacker)->get(route('salesman.customers.show', $customer))->assertNotFound();
        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))->assertOk()->assertSee($salesman->name)->assertSee($dealer->name);
    }

    public function test_allocated_vehicles_remain_visible_without_date_slots_and_cross_dealer_slot_is_rejected(): void
    {
        [$dealer, $salesman] = $this->salesmanContext('Mitsubishi Uttara');
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $models = collect(['Outlander', 'Xforce', 'Triton', 'Pajero Sport'])->map(function ($name) use ($dealer) {
            $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => $name]);
            DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
            return $vehicle;
        });
        $unallocated = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Unallocated Global Model']);
        $otherDealer = Dealer::factory()->create();
        $otherAllocation = DealerVehicleAllocation::create(['dealer_id' => $otherDealer->id, 'vehicle_id' => $unallocated->id, 'quantity' => 1, 'status' => 'active']);
        $otherSlot = TestDriveSlot::create(['vehicle_id' => $unallocated->id, 'dealer_vehicle_allocation_id' => $otherAllocation->id, 'slot_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '10:30']);

        $response = $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $customer));
        foreach ($models as $vehicle) $response->assertSee($vehicle->name);
        $response->assertDontSee($unallocated->name)->assertSee('No slots available for this date.')->assertDontSee('name="scheduled_time"', false);
        $this->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $otherSlot->id, 'vehicle_id' => $unallocated->id, 'dealer_id' => $otherDealer->id])->assertNotFound();
    }

    public function test_complete_customer_test_drive_booking_payment_chain_preserves_allocation_quantity(): void
    {
        [$dealer, $salesman] = $this->salesmanContext('Mitsubishi Uttara');
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Outlander', 'price' => 5000000]);
        $allocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'quantity' => 2, 'status' => 'active']);
        $slot = TestDriveSlot::create(['vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => today()->addDays(2), 'start_time' => '10:00', 'end_time' => '10:30']);

        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slot->id, 'salesman_id' => 999, 'dealer_id' => 999])->assertRedirect();
        $testDrive = TestDrive::firstOrFail();
        $this->assertSame([$customer->id, $salesman->id, $vehicle->id, $slot->id], [$testDrive->customer_id, $testDrive->salesman_id, $testDrive->vehicle_id, $testDrive->slot_id]);
        $this->assertSame($allocation->id, $testDrive->slot->dealer_vehicle_allocation_id);

        $otherSalesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $this->actingAs($otherSalesman)->get(route('salesman.test-drives.show', $testDrive))->assertNotFound();
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk()->assertSee($customer->name);
        $this->get(route('dealer.inventory'))->assertOk()->assertSee($vehicle->name)->assertSee('2');

        $this->actingAs($salesman)->put(route('salesman.test-drives.update', $testDrive), ['slot_id' => $slot->id, 'status' => 'completed', 'notes' => 'Customer completed drive.'])->assertRedirect();
        $this->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), [
            'booking_date' => today()->toDateString(), 'expected_delivery_date' => today()->addDays(20)->toDateString(), 'booking_amount' => 500000,
            'vehicle_price' => 1, 'total_paid' => 9999999, 'due_amount' => 0, 'vehicle_id' => 999, 'customer_id' => 999, 'salesman_id' => 999, 'dealer_id' => 999, 'role' => 'admin',
        ])->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame('500000.00', $booking->total_paid);
        $this->assertSame('4500000.00', $booking->due_amount);
        $this->assertSame($vehicle->id, $booking->vehicle_id);
        $this->assertSame(2, $allocation->fresh()->quantity);
        $this->assertNotSame('sold', $vehicle->fresh()->status);
    }

    private function salesmanContext(string $dealerName): array
    {
        $dealer = Dealer::factory()->create(['name' => $dealerName]);
        return [$dealer, User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id])];
    }

    private function customerPayload(): array
    {
        return ['name' => 'Temporary Ownership Customer', 'phone' => '01700000000', 'email' => 'ownership@example.test', 'address' => 'Dhaka', 'city' => 'Dhaka', 'status' => 'new'];
    }
}
