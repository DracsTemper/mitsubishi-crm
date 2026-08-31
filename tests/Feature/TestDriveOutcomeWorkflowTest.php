<?php

namespace Tests\Feature;

use App\Enums\TestDriveOutcome;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TestDriveOutcomeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_completed_test_drive_can_record_booking_outcome_and_use_existing_booking_payment_workflow(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();

        $this->actingAs($salesman)->post(route('salesman.test-drives.outcome.store', $testDrive), ['outcome' => 'booking'])
            ->assertRedirect(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]));
        $this->assertSame(TestDriveOutcome::Booking, $testDrive->fresh()->outcome);

        $this->post(route('salesman.customers.test-drives.book.store', [$customer, $testDrive]), [
            'booking_date' => '2026-08-31', 'expected_delivery_date' => '2026-09-20', 'booking_amount' => 500000,
        ])->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame('500000.00', $booking->total_paid);
        $this->assertSame('4500000.00', $booking->due_amount);
        $this->get(route('salesman.customers.test-drives.book.create', [$customer, $testDrive]))->assertNotFound();
    }

    public function test_completed_test_drive_can_record_valid_follow_up_and_queue_is_salesman_scoped(): void
    {
        [, $salesman, , , $testDrive] = $this->context();
        [, $otherSalesman, , , $otherDrive] = $this->context('Other Dealer');
        $otherDrive->update(['outcome' => TestDriveOutcome::FollowUp, 'follow_up_date' => '2026-09-12', 'decided_at' => now()]);

        $this->actingAs($salesman)->post(route('salesman.test-drives.outcome.store', $testDrive), [
            'outcome' => 'follow_up', 'follow_up_date' => '2026-09-10', 'outcome_notes' => 'Discuss purchase timing with family.',
        ])->assertRedirect(route('salesman.test-drives.show', $testDrive));
        $testDrive->refresh();
        $this->assertSame(TestDriveOutcome::FollowUp, $testDrive->outcome);
        $this->assertSame('2026-09-10', $testDrive->follow_up_date->toDateString());

        $response = $this->get(route('salesman.follow-ups.index'));
        $response->assertOk()->assertSee($testDrive->customer->name)->assertSee('Discuss purchase timing')->assertDontSee($otherDrive->customer->name);
        $this->actingAs($otherSalesman)->get(route('salesman.follow-ups.index'))->assertOk()->assertSee($otherDrive->customer->name)->assertDontSee($testDrive->customer->name);
    }

    public function test_follow_up_date_is_required_and_cannot_be_in_the_past(): void
    {
        [, $salesman, , , $testDrive] = $this->context();
        $route = route('salesman.test-drives.outcome.store', $testDrive);

        $this->actingAs($salesman)->post($route, ['outcome' => 'follow_up'])->assertSessionHasErrors('follow_up_date');
        $this->post($route, ['outcome' => 'follow_up', 'follow_up_date' => '2026-08-30'])->assertSessionHasErrors('follow_up_date');
        $this->assertNull($testDrive->fresh()->outcome);
    }

    public function test_another_test_drive_outcome_continues_to_authoritative_scheduling_and_preserves_original(): void
    {
        [, $salesman, $customer, $allocation, $original] = $this->context();

        $this->actingAs($salesman)->post(route('salesman.test-drives.outcome.store', $original), ['outcome' => 'another_test_drive'])
            ->assertRedirect(route('salesman.customers.test-drives.create', ['customer' => $customer, 'from_test_drive' => $original->id]));
        $this->post(route('salesman.customers.test-drives.store', $customer), [
            'allocation_id' => $allocation->id, 'slot_date' => '2026-09-01', 'start_time' => '16:00',
        ])->assertRedirect();

        $this->assertSame(TestDriveOutcome::AnotherTestDrive, $original->fresh()->outcome);
        $this->assertSame(2, TestDrive::where('customer_id', $customer->id)->count());
        $this->assertDatabaseHas('test_drives', ['customer_id' => $customer->id, 'scheduled_date' => '2026-09-01 00:00:00', 'scheduled_time' => '16:00']);
    }

    public function test_lost_outcome_requires_reason_and_other_requires_explanation_without_deleting_history(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();
        $route = route('salesman.test-drives.outcome.store', $testDrive);

        $this->actingAs($salesman)->post($route, ['outcome' => 'lost'])->assertSessionHasErrors('loss_reason');
        $this->post($route, ['outcome' => 'lost', 'loss_reason' => 'other'])->assertSessionHasErrors('outcome_notes');
        $this->post($route, ['outcome' => 'lost', 'loss_reason' => 'price_too_high', 'outcome_notes' => 'Customer budget is lower.'])->assertRedirect();

        $testDrive->refresh();
        $this->assertSame(TestDriveOutcome::Lost, $testDrive->outcome);
        $this->assertSame('price_too_high', $testDrive->loss_reason->value);
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($testDrive->fresh());
    }

    public function test_only_completed_owned_test_drive_can_receive_one_immutable_decision(): void
    {
        [, $salesman, , , $testDrive] = $this->context();
        $testDrive->update(['status' => TestDriveStatus::Scheduled]);
        $route = route('salesman.test-drives.outcome.store', $testDrive);
        $this->actingAs($salesman)->post($route, ['outcome' => 'booking'])->assertSessionHasErrors('outcome');

        $testDrive->update(['status' => TestDriveStatus::Completed]);
        $this->post($route, ['outcome' => 'follow_up', 'follow_up_date' => '2026-09-10'])->assertRedirect();
        $this->post($route, ['outcome' => 'lost', 'loss_reason' => 'not_ready'])->assertSessionHasErrors('outcome');
        $this->assertSame(TestDriveOutcome::FollowUp, $testDrive->fresh()->outcome);
    }

    public function test_cross_salesman_guest_unassigned_and_other_roles_cannot_record_outcomes(): void
    {
        [, $owner, , , $testDrive] = $this->context();
        [, $otherSalesman] = $this->context('Cross Dealer');
        $route = route('salesman.test-drives.outcome.store', $testDrive);

        $this->post($route, ['outcome' => 'booking'])->assertRedirect(route('login'));
        $this->actingAs($otherSalesman)->post($route, ['outcome' => 'booking'])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]))->post($route, ['outcome' => 'booking'])->assertForbidden();
        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post($route, ['outcome' => 'booking'])->assertForbidden();
        }
        $this->assertNull($testDrive->fresh()->outcome);
        $this->assertNotNull($owner);
    }

    public function test_detail_and_customer_history_present_recorded_decision(): void
    {
        [, $salesman, $customer, , $testDrive] = $this->context();
        $testDrive->update(['outcome' => TestDriveOutcome::Lost, 'loss_reason' => 'financing_issue', 'outcome_notes' => 'Loan terms did not work.', 'decided_at' => now()]);

        $this->actingAs($salesman)->get(route('salesman.test-drives.show', $testDrive))->assertOk()->assertSee('Not Interested / Lost')->assertSee('Financing Issue')->assertSee('Loan terms did not work.');
        $this->get(route('salesman.customers.show', $customer))->assertOk()->assertSee('CUSTOMER DECISION HISTORY')->assertSee('Financing Issue');
    }

    private function context(string $dealerName = 'Outcome Dealer'): array
    {
        $dealer = Dealer::factory()->create(['name' => $dealerName]);
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => null, 'price' => 5000000]);
        $allocation = DealerVehicleAllocation::create(['dealer_id' => $dealer->id, 'vehicle_id' => $vehicle->id, 'quantity' => 1, 'status' => 'active']);
        $slot = TestDriveSlot::create(['vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocation->id, 'slot_date' => '2026-09-01', 'start_time' => '10:00', 'end_time' => '10:30']);
        $testDrive = TestDrive::create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'salesman_id' => $salesman->id, 'slot_id' => $slot->id, 'scheduled_date' => '2026-09-01', 'scheduled_time' => '10:00', 'status' => TestDriveStatus::Completed]);
        return [$dealer, $salesman, $customer, $allocation, $testDrive];
    }
}
