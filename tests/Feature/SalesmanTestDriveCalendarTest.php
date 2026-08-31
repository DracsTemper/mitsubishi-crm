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
use Database\Seeders\DevelopmentTestDriveSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanTestDriveCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_is_salesman_only_and_requires_a_dealer(): void
    {
        $this->get(route('salesman.calendar'))->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('salesman.calendar'))->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]))
            ->get(route('salesman.calendar'))->assertForbidden();
    }

    public function test_salesman_sees_only_their_dealer_allocations_and_slots(): void
    {
        [$uttara, $uttaraSalesman] = $this->salesman('Mitsubishi Uttara');
        [$dhanmondi, $dhanmondiSalesman] = $this->salesman('Mitsubishi Dhanmondi');
        [$outlander, $uttaraSlot] = $this->allocatedSlot($uttara, 'Uttara Calendar Alpha');
        [$triton, $dhanmondiSlot] = $this->allocatedSlot($dhanmondi, 'Dhanmondi Calendar Beta');

        $this->actingAs($uttaraSalesman)->get(route('salesman.calendar', ['week' => today()->startOfWeek()->toDateString()]))
            ->assertOk()->assertSee($outlander->name)->assertSee('AVAILABLE')->assertDontSee($triton->name);
        $this->actingAs($dhanmondiSalesman)->get(route('salesman.calendar', ['week' => today()->startOfWeek()->toDateString()]))
            ->assertOk()->assertSee($triton->name)->assertDontSee($outlander->name);
    }

    public function test_available_and_booked_slots_render_authoritative_state(): void
    {
        [$dealer, $salesman] = $this->salesman('Mitsubishi Uttara');
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Rahim Calendar Customer']);
        [$vehicle, $available] = $this->allocatedSlot($dealer, 'Outlander');
        $booked = TestDriveSlot::create([
            'vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $available->dealer_vehicle_allocation_id,
            'slot_date' => today()->addDay(), 'start_time' => '11:00', 'end_time' => '11:30',
        ]);
        TestDrive::create([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'salesman_id' => $salesman->id,
            'slot_id' => $booked->id, 'scheduled_date' => $booked->slot_date, 'scheduled_time' => $booked->start_time,
            'status' => TestDriveStatus::Scheduled,
        ]);

        $this->actingAs($salesman)->get(route('salesman.calendar', ['week' => today()->startOfWeek()->toDateString()]))
            ->assertOk()->assertSee('AVAILABLE')->assertSee('BOOKED')->assertSee('Rahim Calendar Customer');
    }

    public function test_owned_customer_can_book_available_slot_and_duplicate_is_rejected(): void
    {
        [$dealer, $salesman] = $this->salesman('Mitsubishi Uttara');
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        [, $slot] = $this->allocatedSlot($dealer, 'Outlander');

        $this->actingAs($salesman)->post(route('salesman.calendar.test-drives.store'), [
            'customer_id' => $customer->id, 'slot_id' => $slot->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('test_drives', [
            'customer_id' => $customer->id, 'salesman_id' => $salesman->id, 'slot_id' => $slot->id,
        ]);

        $this->post(route('salesman.calendar.test-drives.store'), [
            'customer_id' => $customer->id, 'slot_id' => $slot->id,
        ])->assertSessionHasErrors('slot_id');
        $this->assertSame(1, TestDrive::where('slot_id', $slot->id)->count());
    }

    public function test_forged_customer_vehicle_slot_and_dealer_cannot_broaden_scope(): void
    {
        [$dealer, $salesman] = $this->salesman('Mitsubishi Uttara');
        [$otherDealer, $otherSalesman] = $this->salesman('Mitsubishi Dhanmondi');
        $ownedCustomer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $otherCustomer = Customer::factory()->create(['salesman_id' => $otherSalesman->id]);
        [$otherVehicle, $otherSlot] = $this->allocatedSlot($otherDealer, 'Pajero Cross Dealer');
        [, $ownedSlot] = $this->allocatedSlot($dealer, 'Outlander');

        $this->actingAs($salesman)->post(route('salesman.calendar.test-drives.store'), [
            'customer_id' => $otherCustomer->id, 'slot_id' => $ownedSlot->id,
        ])->assertNotFound();
        $this->post(route('salesman.calendar.test-drives.store'), [
            'customer_id' => $ownedCustomer->id, 'slot_id' => $otherSlot->id,
        ])->assertNotFound();
        $this->get(route('salesman.calendar', ['vehicle' => $otherVehicle->id]))->assertNotFound();
        $this->post(route('salesman.calendar.test-drives.store'), [
            'customer_id' => $ownedCustomer->id, 'slot_id' => $ownedSlot->id, 'dealer_id' => $otherDealer->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('test_drives', 0);
    }

    public function test_customer_schedule_page_and_calendar_share_the_same_slot_state(): void
    {
        [$dealer, $salesman] = $this->salesman('Mitsubishi Uttara');
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        [$vehicle, $slot] = $this->allocatedSlot($dealer, 'Xforce');

        $calendar = $this->actingAs($salesman)->get(route('salesman.calendar', ['week' => today()->startOfWeek()->toDateString()]));
        $schedule = $this->get(route('salesman.customers.test-drives.create', $customer));
        $calendar->assertSee($vehicle->name)->assertSee('AVAILABLE');
        $schedule->assertSee($vehicle->name)->assertSee((string) $slot->id, false)->assertSee('AVAILABLE');

        $this->post(route('salesman.customers.test-drives.store', $customer), ['slot_id' => $slot->id])->assertRedirect();
        $this->get(route('salesman.calendar', ['week' => today()->startOfWeek()->toDateString()]))->assertSee('BOOKED');
        $this->get(route('salesman.customers.test-drives.create', $customer))->assertSee('BOOKED');
    }

    public function test_slot_seeder_covers_every_active_allocation_and_is_idempotent(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $this->allocatedVehicle($dealerA, 'Outlander');
        $this->allocatedVehicle($dealerB, 'Triton');

        $this->seed(DevelopmentTestDriveSlotSeeder::class);
        $firstCount = TestDriveSlot::count();
        $this->seed(DevelopmentTestDriveSlotSeeder::class);

        $this->assertSame(20, $firstCount);
        $this->assertSame($firstCount, TestDriveSlot::count());
        $this->assertSame(0, TestDriveSlot::whereDate('slot_date', '<', today())->count());
        $this->assertSame(2, TestDriveSlot::query()->distinct()->count('dealer_vehicle_allocation_id'));
    }

    private function salesman(string $name): array
    {
        $dealer = Dealer::factory()->create(['name' => $name]);
        return [$dealer, User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id])];
    }

    private function allocatedVehicle(Dealer $dealer, string $name): array
    {
        $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'name' => $name]);
        $allocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
        return [$vehicle, $allocation];
    }

    private function allocatedSlot(Dealer $dealer, string $name): array
    {
        [$vehicle, $allocation] = $this->allocatedVehicle($dealer, $name);
        $slot = TestDriveSlot::create([
            'vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocation->id,
            'slot_date' => today()->addDay(), 'start_time' => '10:00', 'end_time' => '10:30',
        ]);
        return [$vehicle, $slot];
    }
}
