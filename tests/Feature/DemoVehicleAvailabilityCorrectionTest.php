<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentBookingSeeder;
use Database\Seeders\DevelopmentDemoVehicleAvailabilitySeeder;
use Database\Seeders\DevelopmentTestDriveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoVehicleAvailabilityCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_salesman_sees_all_four_models_and_each_has_ten_slots(): void
    {
        [$dealer, $salesman, $customer, $vehicles, $images] = $this->demoContext();
        $this->seed(DevelopmentBookingSeeder::class);

        $response = $this->actingAs($salesman->fresh())->get(route('salesman.customers.test-drives.create', $customer));
        $response->assertOk();
        foreach (DevelopmentDemoVehicleAvailabilitySeeder::VEHICLE_NAMES as $name) {
            $response->assertSee($name);
            $vehicle = Vehicle::query()->where('name', $name)->firstOrFail();
            $this->assertTrue($vehicle->demoAllocations()->where('dealer_id', $dealer->id)->where('status', 'active')->exists());
            $this->assertSame(10, $vehicle->testDriveSlots()->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $dealer->id))->count());
            $this->assertSame($images[$name], $vehicle->image);
        }
        $this->assertSame(40, TestDriveSlot::query()->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $dealer->id))->count());
        $this->assertSame(2, TestDriveSlot::query()->whereHas('testDrive')->count());
        $this->assertSame(38, TestDriveSlot::query()->whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $dealer->id))->whereDoesntHave('testDrive')->count());
    }

    public function test_cross_dealer_vehicle_remains_hidden_and_its_slot_is_rejected(): void
    {
        [, $salesman, $customer] = $this->demoContext();
        $this->seed(DevelopmentBookingSeeder::class);
        $otherDealer = Dealer::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['dealer_id' => $otherDealer->id, 'name' => 'Other Dealer Model']);
        $slot = TestDriveSlot::query()->create(['vehicle_id' => $otherVehicle->id, 'slot_date' => '2026-09-02', 'start_time' => '10:00', 'end_time' => '10:30']);

        $this->actingAs($salesman->fresh())->get(route('salesman.customers.test-drives.create', $customer))->assertOk()->assertDontSee($otherVehicle->name);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slot->id])->assertNotFound();
    }

    public function test_demo_correction_is_idempotent_across_three_runs_without_duplicates(): void
    {
        [, , , $vehicles] = $this->demoContext();
        for ($run = 0; $run < 3; $run++) {
            $this->seed(DevelopmentBookingSeeder::class);
        }
        $this->assertDatabaseCount('vehicles', 4);
        $this->assertSame(10 * \App\Models\DealerVehicleAllocation::query()->where('status', 'active')->count(), TestDriveSlot::count());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame(1, User::query()->where('email', DevelopmentTestDriveSeeder::SALESMAN_EMAIL)->count());
        foreach ($vehicles as $vehicle) {
            foreach ($vehicle->fresh()->demoAllocations()->where('status', 'active')->get() as $allocation) {
                $this->assertSame(10, $allocation->testDriveSlots()->count());
            }
        }
    }

    private function demoContext(): array
    {
        $dealer = Dealer::factory()->create(['name' => 'Mitsubishi Uttara', 'code' => 'MUT-001']);
        $salesman = User::factory()->create(['name' => 'Demo Salesman - Arif Hasan', 'email' => DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['name' => 'Demo Customer - Rahim Ahmed', 'email' => DevelopmentTestDriveSeeder::CUSTOMER_EMAIL, 'salesman_id' => $salesman->id]);
        $vehicles = collect();
        $images = [];
        foreach (DevelopmentDemoVehicleAvailabilitySeeder::VEHICLE_NAMES as $index => $name) {
            $owner = $index === 0 ? $dealer : Dealer::factory()->create();
            $image = 'assets/images/vehicles/'.str($name)->slug().'-2026.png';
            $images[$name] = $image;
            $vehicles->push(Vehicle::factory()->create(['dealer_id' => $owner->id, 'name' => $name, 'image' => $image, 'price' => 5000000]));
        }
        return [$dealer, $salesman, $customer, $vehicles, $images];
    }
}
