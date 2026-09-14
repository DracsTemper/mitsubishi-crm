<?php

namespace Tests\Feature;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\TestDrive;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesman_can_book_from_owned_completed_test_drive(): void
    {
        [$dealer, $salesman, $customer, $vehicle, $testDrive] = $this->context();
        $response = $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload([
            'salesman_id' => 999, 'dealer_id' => 999, 'customer_id' => 999, 'vehicle_id' => 999, 'role' => 'admin',
        ]));
        $booking = Booking::query()->firstOrFail();
        $response->assertRedirect(route('salesman.bookings.show', $booking));
        $this->assertSame($salesman->id, $booking->salesman_id);
        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($vehicle->id, $booking->vehicle_id);
        $this->assertSame($testDrive->id, $booking->test_drive_id);
        $this->assertSame($dealer->id, $booking->salesman->dealer_id);
        $this->assertSame('available', $vehicle->fresh()->status);
    }

    public function test_booking_form_is_read_only_context_without_ownership_fields(): void
    {
        [$dealer, $salesman, $customer, $vehicle, $testDrive] = $this->context();
        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]))
            ->assertOk()->assertSee($customer->name)->assertSee($vehicle->name)->assertSee($dealer->name)
            ->assertDontSee('name="salesman_id"', false)->assertDontSee('name="dealer_id"', false)
            ->assertDontSee('name="customer_id"', false)->assertDontSee('name="vehicle_id"', false);
    }

    public function test_incomplete_test_drive_cannot_be_booked(): void
    {
        [, $salesman, $customer, $vehicle, $testDrive] = $this->context(status: TestDriveStatus::Confirmed);
        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]))->assertNotFound();
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cross_salesman_customer_and_test_drive_return_404(): void
    {
        [, $owner, $customer, , $testDrive] = $this->context();
        [, $other] = $this->context();
        $this->actingAs($other)->get(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]))->assertNotFound();
        $this->actingAs($other)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload())->assertNotFound();

        [, , $otherCustomer] = $this->context($owner->dealer);
        $this->actingAs($owner)->post(route('salesman.customers.test-drives.book.store', [$otherCustomer, $testDrive]), $this->payload())->assertNotFound();
    }

    public function test_unrelated_test_drive_cannot_be_attached_to_customer(): void
    {
        [$dealer, $salesman, $customer] = $this->context();
        [, , $otherCustomer, , $otherTestDrive] = $this->context($dealer);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $otherTestDrive]), $this->payload())->assertNotFound();
    }

    public function test_cross_dealer_vehicle_cannot_be_booked(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();
        [, , , $otherVehicle] = $this->context();
        DB::table('test_drives')->where('id', $testDrive->id)->update(['vehicle_id' => $otherVehicle->id]);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_duplicate_booking_request_is_safely_rejected(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();
        $route = route('salesman.customers.test-drives.book.store', [$customer, $testDrive]);
        $this->actingAs($salesman)->post($route, $this->payload())->assertRedirect();
        $this->actingAs($salesman)->post($route, $this->payload())->assertNotFound();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_validation_enforces_dates_and_notes(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();
        $route = route('salesman.customers.test-drives.book.store', [$customer, $testDrive]);
$this->actingAs($salesman)->post($route, $this->payload(['booking_date' => today()->subDay()->toDateString()]))->assertSessionHasErrors('booking_date');
$this->actingAs($salesman)->post($route, $this->payload(['expected_delivery_date' => today()->subDay()->toDateString()]))->assertSessionHasErrors('expected_delivery_date');
        $this->actingAs($salesman)->post($route, $this->payload(['notes' => str_repeat('x', 2001)]))->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_only_assigned_salesman_role_can_access_booking_routes(): void
    {
        [, , $customer, , $testDrive] = $this->context();
        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('salesman.bookings'))->assertForbidden();
        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]))->assertForbidden();
            $this->actingAs($actor)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload())->assertForbidden();
        }
        auth()->logout();
        $this->get(route('salesman.bookings'))->assertRedirect(route('login'));
    }

    public function test_booking_list_and_detail_are_salesman_scoped_and_complete(): void
    {
        [$dealer, $salesman, $customer, $vehicle, $testDrive] = $this->context();
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), $this->payload());
        $booking = Booking::query()->firstOrFail();
        [, $other] = $this->context();
        $this->actingAs($salesman)->get(route('salesman.bookings'))->assertOk()->assertSee($customer->name)->assertSee($vehicle->variant);
        $this->actingAs($salesman)->get(route('salesman.bookings.show', $booking))->assertOk()
            ->assertSee($customer->name)->assertSee($vehicle->name)->assertSee($salesman->name)->assertSee($dealer->name)
            ->assertSee('Awaiting Stock / Chassis Assignment')->assertSee('Related Test Drive');
        $this->actingAs($other)->get(route('salesman.bookings.show', $booking))->assertNotFound();
    }

    public function test_demo_booking_seeder_is_idempotent_and_preserves_demo_chain(): void
    {
        $dealer = Dealer::factory()->create();
        Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Outlander', 'variant' => 'Premium', 'color' => 'White']);
        foreach (['Xforce', 'Triton', 'Pajero Sport'] as $name) {
            Vehicle::factory()->create(['dealer_id' => Dealer::factory()->create()->id, 'name' => $name]);
        }
        $this->seed(DevelopmentBookingSeeder::class);
        $this->seed(DevelopmentBookingSeeder::class);
        $this->assertDatabaseCount('bookings', 1);
        $booking = Booking::query()->with(['testDrive', 'customer', 'vehicle'])->firstOrFail();
        $this->assertSame(TestDriveStatus::Completed, $booking->testDrive->status);
        $this->assertSame('Demo Customer - Rahim Ahmed', $booking->customer->name);
        $this->assertSame('Outlander', $booking->vehicle->name);
        $this->assertDatabaseHas('test_drives', [
            'customer_id' => $booking->customer_id,
            'notes' => '[DEMO-F33] Completed Test Drive ready to book',
            'status' => 'completed',
        ]);
    }

    private function context(?Dealer $dealer = null, TestDriveStatus $status = TestDriveStatus::Completed): array
    {
        $dealer ??= Dealer::factory()->create();
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'status' => 'available']);
        $testDrive = TestDrive::query()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'salesman_id' => $salesman->id, 'scheduled_date' => today()->addDays(3)->toDateString(), 'scheduled_time' => '14:00', 'status' => $status, 'notes' => 'Eligible Test Drive']);
        return [$dealer, $salesman, $customer, $vehicle, $testDrive];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['booking_date' => today()->toDateString(), 'expected_delivery_date' => today()->addDays(10)->toDateString(), 'booking_amount' => 500000, 'notes' => 'Customer confirmed model preference.'], $overrides);
    }
}
