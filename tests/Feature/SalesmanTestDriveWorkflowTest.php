<?php

namespace Tests\Feature;

use App\Enums\TestDriveStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentTestDriveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanTestDriveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesman_can_open_scoped_appointment_list(): void
    {
        [, $salesman, $customer, $vehicle] = $this->workflowContext();
        $appointment = $this->appointment($customer, $vehicle, $salesman);

        $this->actingAs($salesman)->get(route('salesman.test-drives.index'))
            ->assertOk()->assertSee('My Test Drives')->assertSee($customer->name)->assertSee($vehicle->name);
    }

    public function test_salesman_can_schedule_for_owned_customer_and_ownership_inputs_are_ignored(): void
    {
        [$dealer, $salesman, $customer, $vehicle] = $this->workflowContext();
        [, $otherSalesman] = $this->workflowContext();

        $response = $this->actingAs($salesman)->post(
            route('salesman.customers.test-drives.store', $customer),
            $this->storePayload($vehicle, ['salesman_id' => $otherSalesman->id, 'dealer_id' => $dealer->id + 999, 'role' => 'admin']),
        );

        $appointment = TestDrive::query()->firstOrFail();
        $response->assertRedirect(route('salesman.test-drives.show', $appointment));
        $this->assertSame($salesman->id, $appointment->salesman_id);
        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertSame(TestDriveStatus::Scheduled, $appointment->status);
        $this->assertDatabaseMissing('test_drives', ['salesman_id' => $otherSalesman->id]);
    }

    public function test_schedule_page_has_no_salesman_or_dealer_ownership_fields(): void
    {
        [, $salesman, $customer, $vehicle] = $this->workflowContext();

        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $customer))
            ->assertOk()->assertSee($vehicle->name)
            ->assertDontSee('name="salesman_id"', false)
            ->assertDontSee('name="dealer_id"', false)
            ->assertDontSee('name="role"', false);
    }

    public function test_cross_salesman_customer_cannot_be_used(): void
    {
        [$dealer, $salesman, , $vehicle] = $this->workflowContext();
        [, , $otherCustomer] = $this->workflowContext($dealer);

        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $otherCustomer))->assertNotFound();
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $otherCustomer), $this->storePayload($vehicle))->assertNotFound();
        $this->assertDatabaseCount('test_drives', 0);
    }

    public function test_cross_dealer_vehicle_is_rejected_and_not_selectable(): void
    {
        [, $salesman, $customer, $vehicle] = $this->workflowContext();
        [, , , $otherVehicle] = $this->workflowContext();

        $this->actingAs($salesman)->get(route('salesman.customers.test-drives.create', $customer))
            ->assertOk()->assertSee($vehicle->name)->assertDontSee($otherVehicle->name);
        $this->actingAs($salesman)->post(route('salesman.customers.test-drives.store', $customer), $this->storePayload($otherVehicle))
            ->assertNotFound();
        $this->assertDatabaseCount('test_drives', 0);
    }

    public function test_salesman_can_view_and_update_only_their_appointment(): void
    {
        [$dealer, $salesman, $customer, $vehicle] = $this->workflowContext();
        $appointment = $this->appointment($customer, $vehicle, $salesman);
        [, $colleague] = $this->workflowContext($dealer);

        $this->actingAs($salesman)->get(route('salesman.test-drives.show', $appointment))
            ->assertOk()->assertSee($customer->name)->assertSee($vehicle->variant)->assertSee($salesman->name)->assertSee($dealer->name);
        $this->actingAs($colleague)->get(route('salesman.test-drives.show', $appointment))->assertNotFound();
        $this->actingAs($colleague)->put(route('salesman.test-drives.update', $appointment), $this->updatePayload())->assertNotFound();

        $this->actingAs($salesman)->put(route('salesman.test-drives.update', $appointment), $this->updatePayload(['status' => 'confirmed']))
            ->assertRedirect(route('salesman.test-drives.show', $appointment));
        $this->assertDatabaseHas('test_drives', ['id' => $appointment->id, 'status' => 'confirmed', 'salesman_id' => $salesman->id]);
    }

    public function test_status_is_centralized_and_arbitrary_values_are_rejected(): void
    {
        [, $salesman, $customer, $vehicle] = $this->workflowContext();
        $appointment = $this->appointment($customer, $vehicle, $salesman);

        $this->actingAs($salesman)->put(route('salesman.test-drives.update', $appointment), $this->updatePayload(['status' => 'approved']))
            ->assertSessionHasErrors('status');
        $this->assertSame(TestDriveStatus::Scheduled, $appointment->fresh()->status);
    }

    public function test_rescheduling_updates_date_time_status_and_supports_json(): void
    {
        [, $salesman, $customer, $vehicle] = $this->workflowContext();
        $appointment = $this->appointment($customer, $vehicle, $salesman);

        $this->actingAs($salesman)->putJson(route('salesman.test-drives.update', $appointment), $this->updatePayload([
            'slot_id' => $this->slot($vehicle, today()->addDays(8)->toDateString(), '16:00', '16:30')->id, 'status' => 'rescheduled',
        ]))->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Test Drive rescheduled successfully.');

        $appointment->refresh();
        $this->assertSame(today()->addDays(8)->format('Y-m-d'), $appointment->scheduled_date->format('Y-m-d'));
        $this->assertStringStartsWith('16:00', $appointment->scheduled_time);
        $this->assertSame(TestDriveStatus::Rescheduled, $appointment->status);
    }

    public function test_update_cannot_reassign_customer_salesman_dealer_or_vehicle(): void
    {
        [$dealer, $salesman, $customer, $vehicle] = $this->workflowContext();
        $appointment = $this->appointment($customer, $vehicle, $salesman);
        [, $otherSalesman, $otherCustomer, $otherVehicle] = $this->workflowContext();

        $this->actingAs($salesman)->put(route('salesman.test-drives.update', $appointment), $this->updatePayload([
            'customer_id' => $otherCustomer->id, 'vehicle_id' => $otherVehicle->id,
            'salesman_id' => $otherSalesman->id, 'dealer_id' => $dealer->id + 999,
        ]))->assertRedirect(route('salesman.test-drives.show', $appointment));

        $appointment->refresh();
        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertSame($vehicle->id, $appointment->vehicle_id);
        $this->assertSame($salesman->id, $appointment->salesman_id);
    }

    public function test_unassigned_salesman_and_other_roles_are_forbidden(): void
    {
        [, , $customer, $vehicle] = $this->workflowContext();
        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('salesman.test-drives.index'))->assertForbidden();
        $this->actingAs($unassigned)->post(route('salesman.customers.test-drives.store', $customer), $this->storePayload($vehicle))->assertForbidden();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('salesman.test-drives.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('salesman.customers.test-drives.store', $customer), $this->storePayload($vehicle))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [, , $customer, $vehicle] = $this->workflowContext();
        $this->get(route('salesman.test-drives.index'))->assertRedirect(route('login'));
        $this->post(route('salesman.customers.test-drives.store', $customer), $this->storePayload($vehicle))->assertRedirect(route('login'));
    }

    public function test_existing_customer_and_vehicle_pages_remain_operational(): void
    {
        [$dealer, $salesman, $customer, $vehicle] = $this->workflowContext();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))->assertOk()->assertSee('Schedule Test Drive');
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($admin)->get(route('vehicles.show', $vehicle))->assertOk();
        $this->assertSame($dealer->id, $vehicle->dealer_id);
        $this->assertSame($salesman->id, $customer->salesman_id);
    }

    public function test_demo_seeder_is_deterministic_idempotent_and_clearly_identified(): void
    {
        $dealer = Dealer::factory()->create();
        Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Outlander', 'variant' => 'Premium', 'color' => 'White']);

        $this->seed(DevelopmentTestDriveSeeder::class);
        $this->seed(DevelopmentTestDriveSeeder::class);

        $this->assertDatabaseCount('test_drives', 1);
        $this->assertDatabaseHas('users', ['email' => DevelopmentTestDriveSeeder::SALESMAN_EMAIL, 'role' => 'salesman', 'dealer_id' => $dealer->id]);
        $this->assertDatabaseHas('customers', ['email' => DevelopmentTestDriveSeeder::CUSTOMER_EMAIL, 'name' => 'Demo Customer - Rahim Ahmed']);
        $demo = TestDrive::query()->firstOrFail();
        $this->assertSame(\Illuminate\Support\Carbon::today()->addDays(2)->format('Y-m-d'), $demo->scheduled_date->format('Y-m-d'));
        $this->assertSame('15:00', $demo->scheduled_time);
        $this->assertSame(TestDriveStatus::Scheduled, $demo->status);
    }

    /** @return array{Dealer, User, Customer, Vehicle} */
    private function workflowContext(?Dealer $dealer = null): array
    {
        $dealer ??= Dealer::factory()->create();
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Outlander '.fake()->unique()->numerify('###')]);
        $this->slot($vehicle);

        return [$dealer, $salesman, $customer, $vehicle];
    }

    private function appointment(Customer $customer, Vehicle $vehicle, User $salesman): TestDrive
    {
        $slot = $this->slot($vehicle);
        return TestDrive::query()->create([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'salesman_id' => $salesman->id,
            'slot_id' => $slot->id,
            'scheduled_date' => today()->addDays(2)->toDateString(), 'scheduled_time' => '15:00',
            'status' => TestDriveStatus::Scheduled, 'notes' => 'Demo appointment.',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function storePayload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge(['slot_id' => $this->slot($vehicle)->id, 'notes' => 'Customer prefers white.'], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge(['slot_id' => TestDrive::query()->latest('id')->value('slot_id'), 'status' => 'scheduled', 'notes' => 'Updated notes.'], $overrides);
    }

    private function slot(Vehicle $vehicle, string $date = null, string $start = '15:00', string $end = '15:30'): TestDriveSlot
    {
        $date ??= today()->addDays(2)->toDateString();
        return TestDriveSlot::query()->firstOrCreate(['vehicle_id' => $vehicle->id, 'slot_date' => \Illuminate\Support\Carbon::parse($date)->startOfDay(), 'start_time' => $start, 'end_time' => $end]);
    }
}
