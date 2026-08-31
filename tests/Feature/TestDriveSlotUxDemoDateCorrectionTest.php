<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentBookingSeeder;
use Database\Seeders\DevelopmentDemoVehicleAvailabilitySeeder;
use Database\Seeders\DevelopmentTestDriveSeeder;
use Database\Seeders\DevelopmentTestDriveSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TestDriveSlotUxDemoDateCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_date_first_form_maps_all_four_dealer_vehicles_to_five_slots_and_has_clear_empty_state(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');
        [$salesman, $customer] = $this->demoContext();
        $this->seed(DevelopmentBookingSeeder::class);

        $response = $this->actingAs($salesman->fresh())->get(route('salesman.customers.test-drives.create', $customer));
        $response->assertOk()
            ->assertSee('1. Select Date')
            ->assertSee('2. Demo / Test Drive Vehicle')
            ->assertSee('No slots available for this date.')
            ->assertSee('BOOKED')
            ->assertSee('AVAILABLE')
            ->assertDontSee('name="scheduled_time"', false);

        foreach (DevelopmentDemoVehicleAvailabilitySeeder::VEHICLE_NAMES as $name) {
            $vehicle = Vehicle::query()->where('name', $name)->firstOrFail();
            $response->assertSee($name);
            $this->assertSame(5, $vehicle->testDriveSlots()->whereDate('slot_date', '2026-09-02')->count());
        }
    }

    public function test_vehicle_and_slot_data_remain_dealer_scoped_and_cross_dealer_slot_is_rejected(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');
        [$salesman, $customer] = $this->demoContext();
        $this->seed(DevelopmentBookingSeeder::class);
        $otherDealer = Dealer::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['dealer_id' => $otherDealer->id, 'name' => 'Cross Dealer Vehicle']);
        $otherSlot = TestDriveSlot::query()->create(['vehicle_id' => $otherVehicle->id, 'slot_date' => '2026-09-02', 'start_time' => '10:00', 'end_time' => '10:30']);

        $this->actingAs($salesman->fresh())->get(route('salesman.customers.test-drives.create', $customer))
            ->assertOk()->assertDontSee($otherVehicle->name);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $otherSlot->id])
            ->assertNotFound();
    }

    public function test_relative_demo_dates_are_future_and_seeder_is_idempotent(): void
    {
        Carbon::setTestNow('2030-01-10 09:00:00');
        $this->demoContext();
        $this->seed(DevelopmentBookingSeeder::class);
        $this->seed(DevelopmentBookingSeeder::class);

        $dates = DevelopmentTestDriveSlotSeeder::demoDates();
        $this->assertSame(['2030-01-12', '2030-01-15'], array_map(fn (Carbon $date) => $date->toDateString(), $dates));
        $this->assertTrue(collect($dates)->every->isFuture());
        $this->assertDatabaseCount('test_drive_slots', 40);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('test_drives', 2);
        $this->assertSame(2, TestDriveSlot::query()->whereHas('testDrive')->count());
    }

    private function demoContext(): array
    {
        $dealer = Dealer::factory()->create(['name' => 'Mitsubishi Uttara', 'code' => 'MUT-001']);
        $salesman = User::factory()->create(['email' => DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['email' => DevelopmentTestDriveSeeder::CUSTOMER_EMAIL, 'salesman_id' => $salesman->id]);
        foreach (DevelopmentDemoVehicleAvailabilitySeeder::VEHICLE_NAMES as $name) {
            Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => $name, 'price' => 5000000, 'image' => 'assets/images/vehicles/'.str($name)->slug().'-2026.png']);
        }
        return [$salesman, $customer];
    }
}
