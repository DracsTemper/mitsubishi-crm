<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanCustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_salesman_can_open_creation_page_without_ownership_inputs(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();

        $this->actingAs($salesman)->get(route('salesman.customers.create'))
            ->assertOk()
            ->assertSee('Create Customer')
            ->assertSee($salesman->name)
            ->assertSee($dealer->name)
            ->assertDontSee('name="salesman_id"', false)
            ->assertDontSee('name="dealer_id"', false);
    }

    public function test_guest_and_non_salesman_roles_cannot_access_or_submit_registration(): void
    {
        [$dealer] = $this->assignedSalesman();

        $this->get(route('salesman.customers.create'))->assertRedirect(route('login'));
        $this->post(route('salesman.customers.store'), $this->validPayload())->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role, 'dealer_id' => $role === UserRole::Dealer ? $dealer->id : null]);
            $this->actingAs($actor)->get(route('salesman.customers.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('salesman.customers.store'), $this->validPayload())->assertForbidden();
        }
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_ownership_comes_only_from_authenticated_salesman_despite_malicious_inputs(): void
    {
        [$dealerA, $salesmanA] = $this->assignedSalesman();
        [, $salesmanB] = $this->assignedSalesman($dealerA);
        [$dealerB, $salesmanC] = $this->assignedSalesman();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Salesman] as $submittedRole) {
            $email = "tamper-{$submittedRole->value}@example.test";
            $response = $this->actingAs($salesmanA)->post(route('salesman.customers.store'), $this->validPayload([
                'email' => $email,
                'salesman_id' => $submittedRole === UserRole::Salesman ? $salesmanB->id : $salesmanC->id,
                'dealer_id' => $dealerB->id,
                'role' => $submittedRole->value,
            ]));
            $customer = Customer::query()->where('email', $email)->firstOrFail();

            $response->assertRedirect(route('salesman.customers.show', $customer))->assertSessionHas('success');
            $this->assertSame($salesmanA->id, $customer->salesman_id);
            $this->assertTrue($customer->salesman->is($salesmanA));
            $this->assertTrue($customer->salesman->dealer->is($dealerA));
        }
    }

    public function test_unassigned_salesman_cannot_access_or_submit_registration(): void
    {
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);

        $this->actingAs($salesman)->get(route('salesman.customers.create'))->assertForbidden();
        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_validation_and_nullable_fields_follow_existing_conventions(): void
    {
        [, $salesman] = $this->assignedSalesman();

        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload(['name' => null]))->assertSessionHasErrors('name');
        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload(['phone' => null]))->assertSessionHasErrors('phone');
        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload(['status' => 'invalid']))->assertSessionHasErrors('status');

        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload([
            'email' => null,
            'address' => null,
            'city' => null,
            'status' => 'new',
        ]));
        $this->assertDatabaseHas('customers', [
            'salesman_id' => $salesman->id,
            'email' => null,
            'address' => null,
            'city' => null,
            'status' => 'new',
        ]);
    }

    public function test_created_customer_appears_for_owner_and_is_hidden_from_other_salesmen(): void
    {
        [$dealer, $owner] = $this->assignedSalesman();
        [, $colleague] = $this->assignedSalesman($dealer);
        [, $otherDealerSalesman] = $this->assignedSalesman();
        $this->actingAs($owner)->post(route('salesman.customers.store'), $this->validPayload());
        $customer = Customer::query()->where('email', 'new.customer@example.test')->firstOrFail();

        $this->actingAs($owner)->get(route('salesman.customers.index'))->assertOk()->assertSee($customer->name);
        $this->actingAs($owner)->get(route('salesman.customers.show', $customer))->assertOk()->assertSee($owner->name)->assertSee($dealer->name);
        $this->actingAs($colleague)->get(route('salesman.customers.index'))->assertOk()->assertDontSee($customer->name);
        $this->actingAs($colleague)->get(route('salesman.customers.show', $customer))->assertNotFound();
        $this->actingAs($otherDealerSalesman)->get(route('salesman.customers.index'))->assertOk()->assertDontSee($customer->name);
        $this->actingAs($otherDealerSalesman)->get(route('salesman.customers.show', $customer))->assertNotFound();
    }

    public function test_manager_read_scope_and_admin_customer_crud_remain_operational(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();
        $this->actingAs($salesman)->post(route('salesman.customers.store'), $this->validPayload());
        $customer = Customer::query()->where('email', 'new.customer@example.test')->firstOrFail();

        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $this->actingAs($manager)->get(route('dealer.customers.index'))->assertOk()->assertSee($customer->name);
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee($customer->name);
        $this->actingAs($admin)->get(route('customers.edit', $customer))->assertOk();
        $this->actingAs($admin)->put(route('customers.update', $customer), [
            ...$customer->only(['salesman_id', 'name', 'phone', 'email', 'address', 'city', 'status']),
            'name' => 'Admin Updated Customer',
        ])->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Admin Updated Customer']);
    }

    /** @return array{Dealer, User} */
    private function assignedSalesman(?Dealer $dealer = null): array
    {
        $dealer ??= Dealer::factory()->create();

        return [$dealer, User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ])];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Customer',
            'phone' => '+8801700000000',
            'email' => 'new.customer@example.test',
            'address' => '123 Test Road',
            'city' => 'Dhaka',
            'status' => 'new',
        ], $overrides);
    }
}
