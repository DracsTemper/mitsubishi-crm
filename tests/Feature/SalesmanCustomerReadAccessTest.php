<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanCustomerReadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_salesman_customer_list_or_detail(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('salesman.customers.index'))->assertRedirect(route('login'));
        $this->get(route('salesman.customers.show', $customer))->assertRedirect(route('login'));
    }

    public function test_non_salesman_roles_cannot_access_salesman_customer_routes(): void
    {
        $customer = Customer::factory()->create();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('salesman.customers.index'))->assertForbidden();
            $this->actingAs($user)->get(route('salesman.customers.show', $customer))->assertForbidden();
        }
    }

    public function test_list_query_returns_only_authenticated_salesmans_customers(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $colleague = $this->salesman($dealer);
        $otherDealerSalesman = $this->salesman(Dealer::factory()->create());
        $ownedA = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Owned Customer Alpha']);
        $ownedB = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Owned Customer Beta']);
        $colleagueCustomer = Customer::factory()->create(['salesman_id' => $colleague->id, 'name' => 'Same Dealer Hidden Customer']);
        $otherDealerCustomer = Customer::factory()->create(['salesman_id' => $otherDealerSalesman->id, 'name' => 'Other Dealer Hidden Customer']);

        $this->actingAs($salesman)->get(route('salesman.customers.index'))
            ->assertOk()
            ->assertSee($ownedA->name)
            ->assertSee($ownedB->name)
            ->assertDontSee($colleagueCustomer->name)
            ->assertDontSee($otherDealerCustomer->name);
    }

    public function test_salesman_can_view_own_customer_detail(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee($salesman->name)
            ->assertSee($dealer->name)
            ->assertSee('Read only');
    }

    public function test_salesman_cannot_view_colleagues_customer_under_same_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $colleague = $this->salesman($dealer);
        $customer = Customer::factory()->create(['salesman_id' => $colleague->id]);

        $this->actingAs($salesman)
            ->get(route('salesman.customers.show', $customer))
            ->assertNotFound()
            ->assertDontSee($customer->name);
    }

    public function test_salesman_cannot_view_customer_from_another_dealer(): void
    {
        $salesman = $this->salesman(Dealer::factory()->create());
        $otherSalesman = $this->salesman(Dealer::factory()->create());
        $customer = Customer::factory()->create(['salesman_id' => $otherSalesman->id]);

        $this->actingAs($salesman)
            ->get(route('salesman.customers.show', $customer))
            ->assertNotFound()
            ->assertDontSee($customer->name);
    }

    public function test_unassigned_salesman_cannot_access_customer_workspace(): void
    {
        $unassigned = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => null,
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($unassigned)->get(route('salesman.customers.index'))->assertForbidden();
        $this->actingAs($unassigned)->get(route('salesman.customers.show', $customer))->assertForbidden();
    }

    public function test_browser_supplied_salesman_id_cannot_change_list_or_detail_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $otherSalesman = $this->salesman($dealer);
        $owned = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Authenticated Owner Customer']);
        $other = Customer::factory()->create(['salesman_id' => $otherSalesman->id, 'name' => 'Injected Owner Customer']);

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['salesman_id' => $otherSalesman->id]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->actingAs($salesman)->get(route('salesman.customers.show', [$owned, 'salesman_id' => $otherSalesman->id]))
            ->assertOk()->assertSee($owned->name);
        $this->actingAs($salesman)->get(route('salesman.customers.show', [$other, 'salesman_id' => $salesman->id]))
            ->assertNotFound();
    }

    public function test_browser_supplied_dealer_id_cannot_broaden_customer_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $otherDealer = Dealer::factory()->create();
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['dealer_id' => $dealer->id]))
            ->assertOk()->assertSee($customer->name);
        $this->actingAs($salesman)->get(route('salesman.customers.index', ['dealer_id' => $otherDealer->id]))
            ->assertForbidden();
    }

    public function test_admin_crud_and_other_role_workspaces_remain_unaffected(): void
    {
        $customer = Customer::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Dealer]);
        $customerUser = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($admin)->get(route('customers.index'))->assertOk();
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk();
        $this->actingAs($customerUser)->get(route('customer.dashboard'))->assertOk();
    }

    private function salesman(Dealer $dealer): User
    {
        return User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);
    }
}
