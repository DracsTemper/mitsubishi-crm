<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerCustomerReadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_manager_customer_list_or_detail(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('dealer.customers.index'))->assertRedirect(route('login'));
        $this->get(route('dealer.customers.show', $customer))->assertRedirect(route('login'));
    }

    public function test_salesman_and_customer_roles_cannot_access_manager_customer_routes(): void
    {
        $customer = Customer::factory()->create();

        foreach ([UserRole::Salesman, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('dealer.customers.index'))->assertForbidden();
            $this->actingAs($user)->get(route('dealer.customers.show', $customer))->assertForbidden();
        }
    }

    public function test_assigned_manager_sees_customers_from_all_salesmen_in_their_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        $otherSalesman = $this->salesman(Dealer::factory()->create());
        $customerA = Customer::factory()->create(['salesman_id' => $salesmanA->id, 'name' => 'Dealer Customer Alpha']);
        $customerB = Customer::factory()->create(['salesman_id' => $salesmanB->id, 'name' => 'Dealer Customer Beta']);
        $otherCustomer = Customer::factory()->create(['salesman_id' => $otherSalesman->id, 'name' => 'Other Dealer Customer']);

        $this->actingAs($manager)->get(route('dealer.customers.index'))
            ->assertOk()
            ->assertSee($customerA->name)
            ->assertSee($customerB->name)
            ->assertSee($salesmanA->name)
            ->assertSee($salesmanB->name)
            ->assertDontSee($otherCustomer->name);
    }

    public function test_assigned_manager_can_view_same_dealer_customer_detail(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesman = $this->salesman($dealer);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee($salesman->name)
            ->assertSee($dealer->name)
            ->assertSee('Read only');
    }

    public function test_manager_cannot_list_or_view_another_dealers_customer(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $ownedSalesman = $this->salesman($dealer);
        $otherSalesman = $this->salesman(Dealer::factory()->create());
        $owned = Customer::factory()->create(['salesman_id' => $ownedSalesman->id, 'name' => 'Visible Dealer Customer']);
        $other = Customer::factory()->create(['salesman_id' => $otherSalesman->id, 'name' => 'Hidden Dealer Customer']);

        $this->actingAs($manager)->get(route('dealer.customers.index'))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get(route('dealer.customers.show', $other))
            ->assertNotFound()->assertDontSee($other->name);
    }

    public function test_unassigned_manager_is_denied_customer_workspace(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Dealer,
            'dealer_id' => null,
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($manager)->get(route('dealer.customers.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertForbidden();
    }

    public function test_browser_inputs_cannot_broaden_manager_customer_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesman = $this->salesman($dealer);
        $otherDealer = Dealer::factory()->create();
        $otherSalesman = $this->salesman($otherDealer);
        $owned = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Authenticated Dealer Customer']);
        $other = Customer::factory()->create(['salesman_id' => $otherSalesman->id, 'name' => 'Injected Dealer Customer']);

        $this->actingAs($manager)->get(route('dealer.customers.index', ['salesman_id' => $otherSalesman->id]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get(route('dealer.customers.show', [$other, 'salesman_id' => $salesman->id]))
            ->assertNotFound();
        $this->actingAs($manager)->get(route('dealer.customers.index', ['dealer_id' => $otherDealer->id]))
            ->assertForbidden();
    }

    public function test_salesman_scope_and_admin_customer_crud_remain_unchanged(): void
    {
        $dealer = Dealer::factory()->create();
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        $customerA = Customer::factory()->create(['salesman_id' => $salesmanA->id, 'name' => 'Salesman A Customer']);
        $customerB = Customer::factory()->create(['salesman_id' => $salesmanB->id, 'name' => 'Salesman B Customer']);

        $this->actingAs($salesmanA)->get(route('salesman.customers.index'))
            ->assertOk()->assertSee($customerA->name)->assertDontSee($customerB->name);
        $this->actingAs($salesmanA)->get(route('salesman.customers.show', $customerB))->assertNotFound();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()->assertSee($customerA->name)->assertSee($customerB->name);
        $this->actingAs($admin)->get(route('customers.edit', $customerA))->assertOk();
    }

    private function manager(Dealer $dealer): User
    {
        return User::factory()->create([
            'role' => UserRole::Dealer,
            'dealer_id' => $dealer->id,
        ]);
    }

    private function salesman(Dealer $dealer): User
    {
        return User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);
    }
}
