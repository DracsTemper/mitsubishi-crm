<?php

namespace Tests\Feature;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentBookingSeeder;
use Database\Seeders\DevelopmentDemoVehicleAllocationSeeder;
use Database\Seeders\DevelopmentTestDriveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoVehicleAllocationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_controls_allocations_and_same_model_can_be_at_multiple_dealers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Allocation Model']);
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();

        $this->actingAs($admin)->get(route('inventory'))->assertOk()->assertSee('Master Vehicle Catalog');
        $this->post(route('vehicles.demo-allocations.store', $vehicle), ['dealer_id' => $first->id, 'quantity' => 2, 'status' => 'active'])->assertRedirect();
        $this->post(route('vehicles.demo-allocations.store', $vehicle), ['dealer_id' => $second->id, 'quantity' => 1, 'status' => 'active'])->assertRedirect();
        $duplicate = $this->post(route('vehicles.demo-allocations.store', $vehicle), ['dealer_id' => $first->id, 'quantity' => 3, 'status' => 'active']);
        $duplicate->assertSessionHasErrors('dealer_id');
        $this->assertDatabaseCount('dealer_vehicle_allocations', 2);

        $allocation = DealerVehicleAllocation::where('dealer_id', $first->id)->firstOrFail();
        $this->put(route('vehicles.demo-allocations.update', [$vehicle, $allocation]), ['quantity' => 4, 'status' => 'active'])->assertRedirect();
        $this->assertSame(4, $allocation->fresh()->quantity);
        $this->delete(route('vehicles.demo-allocations.destroy', [$vehicle, $allocation]))->assertRedirect();
        $this->assertDatabaseMissing('dealer_vehicle_allocations', ['id' => $allocation->id]);
    }

    public function test_manager_and_salesman_see_only_their_active_outlet_allocations(): void
    {
        $dealer = Dealer::factory()->create(['name' => 'Scoped Outlet']);
        $otherDealer = Dealer::factory()->create(['name' => 'Other Outlet']);
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $visible = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Allocated Here']);
        $hidden = Vehicle::factory()->create(['dealer_id' => null, 'name' => 'Allocated Elsewhere']);
        $allocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $visible->id, 'quantity' => 3, 'status' => 'active']);
        DealerVehicleAllocation::create(['dealer_id' => $otherDealer->id, 'vehicle_id' => $hidden->id, 'quantity' => 2, 'status' => 'active']);
        TestDriveSlot::create(['vehicle_id' => $visible->id, 'dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '10:30']);

        $this->actingAs($manager)->get(route('dealer.inventory'))->assertOk()->assertSee('Allocated Here')->assertSee('3')->assertDontSee('Allocated Elsewhere');
        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $customer))->assertOk()->assertSee('Allocated Here')->assertDontSee('Allocated Elsewhere');
    }

    public function test_slots_are_independent_per_dealer_allocation_and_cross_dealer_booking_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create(['dealer_id' => null]);
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $allocationA = DealerVehicleAllocation::create(['dealer_id' => $dealerA->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
        $allocationB = DealerVehicleAllocation::create(['dealer_id' => $dealerB->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
        $slotA = TestDriveSlot::create(['vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocationA->id, 'slot_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '10:30']);
        $slotB = TestDriveSlot::create(['vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocationB->id, 'slot_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '10:30']);
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealerA->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slotB->id])->assertNotFound();
        $this->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slotA->id])->assertRedirect();
        $this->assertFalse($slotB->fresh()->is_booked);
        $this->assertTrue($slotA->fresh()->is_booked);
    }

    public function test_demo_catalog_and_allocations_are_idempotent_and_preserve_booking_chain_and_images(): void
    {
        $dealer = Dealer::factory()->create(['name' => 'Mitsubishi Uttara', 'code' => 'MUT-001']);
        User::factory()->create(['email' => DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        foreach (['Outlander', 'Xforce', 'Triton', 'Pajero Sport'] as $name) {
            Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => $name, 'price' => 5000000, 'image' => 'assets/images/vehicles/'.str($name)->slug().'-2026.png']);
        }
        $this->seed(DevelopmentBookingSeeder::class);
        $images = Vehicle::whereIn('name', ['Outlander', 'Xforce', 'Triton', 'Pajero Sport'])->pluck('image', 'name');
        $testDriveIds = TestDrive::pluck('id');
        $bookingIds = Booking::pluck('id');
        $this->seed(DevelopmentDemoVehicleAllocationSeeder::class);
        $this->seed(DevelopmentDemoVehicleAllocationSeeder::class);

        foreach ($images as $name => $image) $this->assertSame($image, Vehicle::where('name', $name)->value('image'));
        $this->assertSame($testDriveIds->all(), TestDrive::pluck('id')->all());
        $this->assertSame($bookingIds->all(), Booking::pluck('id')->all());
        $this->assertTrue(Vehicle::where('name', 'Lancer Evolution')->where('description', 'like', '%Historical discontinued%')->exists());
        $this->assertSame(8, Vehicle::count());
        $this->assertSame(7, DealerVehicleAllocation::where('dealer_id', $dealer->id)->count());
        $this->assertFalse(\Schema::hasColumn('vehicles', 'chassis_number'));
        $this->assertFalse(\Schema::hasColumn('vehicles', 'vin'));
        $this->assertFalse(\Schema::hasColumn('vehicles', 'engine_number'));
    }
}
