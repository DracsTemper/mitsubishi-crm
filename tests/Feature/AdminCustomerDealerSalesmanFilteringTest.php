<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerDealerSalesmanFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_filters_by_dealer_and_excludes_other_dealers(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $a1 = $this->customer($this->salesman($dealerA), 'Dealer A Customer One');
        $a2 = $this->customer($this->salesman($dealerA), 'Dealer A Customer Two');
        $b1 = $this->customer($this->salesman($dealerB), 'Dealer B Customer');

        $this->actingAs($admin)->get(route('customers.index', ['dealer_id' => $dealerA->id]))
            ->assertOk()->assertSee($a1->name)->assertSee($a2->name)->assertDontSee($b1->name);
    }

    public function test_admin_filters_by_salesman_and_excludes_other_salesmen(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        $owned = $this->customer($salesmanA, 'Salesman A Customer');
        $other = $this->customer($salesmanB, 'Salesman B Customer');

        $this->actingAs($admin)->get(route('customers.index', ['salesman_id' => $salesmanA->id]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
    }

    public function test_dealer_and_salesman_filters_require_both_conditions(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $salesmanA = $this->salesman($dealerA);
        $salesmanB = $this->salesman($dealerB);
        $owned = $this->customer($salesmanA, 'Matching Dealer Salesman Customer');
        $other = $this->customer($salesmanB, 'Other Dealer Salesman Customer');

        $this->actingAs($admin)->get(route('customers.index', ['dealer_id' => $dealerA->id, 'salesman_id' => $salesmanA->id]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $mismatch = $this->get(route('customers.index', ['dealer_id' => $dealerB->id, 'salesman_id' => $salesmanA->id]));
        $mismatch->assertOk()->assertDontSee($owned->name)->assertDontSee($other->name);
        $this->assertCount(0, $mismatch->viewData('customers')->items());
    }

    public function test_invalid_or_non_salesman_filter_ids_are_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $manager = $this->user(UserRole::Dealer);

        $this->actingAs($admin)->from(route('customers.index'))
            ->get(route('customers.index', ['salesman_id' => $manager->id]))
            ->assertRedirect(route('customers.index'))->assertSessionHasErrors('salesman_id');
        $this->from(route('customers.index'))->get(route('customers.index', ['salesman_id' => 999999]))
            ->assertRedirect(route('customers.index'))->assertSessionHasErrors('salesman_id');
        $this->from(route('customers.index'))->get(route('customers.index', ['dealer_id' => 999999]))
            ->assertRedirect(route('customers.index'))->assertSessionHasErrors('dealer_id');
    }

    public function test_search_status_dealer_and_salesman_filters_combine_with_and_semantics(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $salesmanA = $this->salesman($dealerA);
        $salesmanA2 = $this->salesman($dealerA);
        $salesmanB = $this->salesman($dealerB);
        $match = $this->customer($salesmanA, 'Rahim Complete Match', status: 'hot');
        $wrongStatus = $this->customer($salesmanA, 'Rahim Wrong Status', status: 'new');
        $wrongSalesman = $this->customer($salesmanA2, 'Rahim Wrong Salesman', status: 'hot');
        $wrongDealer = $this->customer($salesmanB, 'Rahim Wrong Dealer', status: 'hot');

        $base = ['q' => 'Rahim'];
        $this->actingAs($admin)->get(route('customers.index', $base + ['dealer_id' => $dealerA->id]))
            ->assertOk()->assertSee($match->name)->assertSee($wrongSalesman->name)->assertDontSee($wrongDealer->name);
        $this->get(route('customers.index', $base + ['salesman_id' => $salesmanA->id]))
            ->assertOk()->assertSee($match->name)->assertSee($wrongStatus->name)->assertDontSee($wrongSalesman->name);
        $this->get(route('customers.index', ['status' => 'hot', 'dealer_id' => $dealerA->id]))
            ->assertOk()->assertSee($match->name)->assertSee($wrongSalesman->name)->assertDontSee($wrongStatus->name);
        $this->get(route('customers.index', ['status' => 'hot', 'salesman_id' => $salesmanA->id]))
            ->assertOk()->assertSee($match->name)->assertDontSee($wrongStatus->name)->assertDontSee($wrongSalesman->name);
        $this->get(route('customers.index', ['q' => 'Rahim', 'status' => 'hot', 'dealer_id' => $dealerA->id, 'salesman_id' => $salesmanA->id]))
            ->assertOk()->assertSee($match->name)->assertDontSee($wrongStatus->name)->assertDontSee($wrongSalesman->name)->assertDontSee($wrongDealer->name);
    }

    public function test_pagination_preserves_every_filter_and_clear_link_resets_them(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        foreach (range(1, 11) as $number) {
            $this->customer($salesman, sprintf('Function 28 Page %02d', $number), status: 'hot');
        }

        $filters = ['q' => 'Function 28', 'status' => 'hot', 'dealer_id' => $dealer->id, 'salesman_id' => $salesman->id];
        $response = $this->actingAs($admin)->get(route('customers.index', $filters));
        $response->assertOk()->assertSee('Clear')->assertSee('href="'.route('customers.index').'"', false);
        $this->assertCount(10, $response->viewData('customers')->items());
        $response->assertSee("q=Function%2028&amp;status=hot&amp;dealer_id={$dealer->id}&amp;salesman_id={$salesman->id}&amp;page=2", false);
        $this->assertCount(1, $this->get(route('customers.index', $filters + ['page' => 2]))->viewData('customers')->items());
        $this->get(route('customers.index'))->assertOk()->assertSee('Function 28 Page 01');
    }

    public function test_unassigned_salesman_customer_is_global_and_filterable_by_salesman(): void
    {
        $admin = $this->user(UserRole::Admin);
        $unassigned = $this->salesman();
        $customer = $this->customer($unassigned, 'Unassigned Salesman Filter Customer');

        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee($customer->name)->assertSee('No Dealer assigned');
        $this->get(route('customers.index', ['salesman_id' => $unassigned->id]))->assertOk()->assertSee($customer->name);
    }

    public function test_authorization_role_tampering_and_customer_ownership_remain_unchanged(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $customer = $this->customer($salesman, 'Ownership Must Not Change');

        $this->actingAs($admin)->get(route('customers.index', ['dealer_id' => $dealer->id, 'role' => UserRole::Customer->value]))
            ->assertOk()->assertSee($customer->name);
        $this->assertSame($salesman->id, $customer->fresh()->salesman_id);
        $this->post(route('logout'));
        $this->get(route('customers.index', ['dealer_id' => $dealer->id]))->assertRedirect(route('login'));
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $this->actingAs($this->user($role))->get(route('customers.index', ['dealer_id' => $dealer->id]))->assertForbidden();
        }
        $this->assertSame($salesman->id, $customer->fresh()->salesman_id);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function salesman(?Dealer $dealer = null): User
    {
        return User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer?->id]);
    }

    private function customer(User $salesman, string $name, string $status = 'new'): Customer
    {
        return Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => $name, 'status' => $status]);
    }
}
