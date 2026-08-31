<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerCustomerSearchFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_searches_customers_across_same_dealer_salesmen_by_name_phone_and_email(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        $rahim = $this->customer($salesmanA, 'Rahim Manager Search', '01726111111', 'rahim.manager@example.test');
        $nusrat = $this->customer($salesmanB, 'Nusrat Dealer Customer', '01826222222', 'nusrat.manager@example.test');

        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Rahim']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Manager Sea']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => '2622']))
            ->assertOk()->assertSee($nusrat->name)->assertDontSee($rahim->name);
        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'rahim.manager@']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
    }

    public function test_status_and_combined_filters_apply_inside_manager_dealer_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        $hotRahim = $this->customer($salesmanA, 'Rahim Hot Manager', status: 'hot');
        $newRahim = $this->customer($salesmanB, 'Rahim New Manager', status: 'new');
        $hotOther = $this->customer($salesmanB, 'Karim Hot Manager', status: 'hot');

        $this->actingAs($manager)->get(route('dealer.customers.index', ['status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertSee($hotOther->name)->assertDontSee($newRahim->name);
        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Rahim', 'status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertDontSee($newRahim->name)->assertDontSee($hotOther->name);
    }

    public function test_empty_search_shows_all_dealer_customers_and_invalid_status_is_rejected(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $first = $this->customer($this->salesman($dealer), 'Manager Empty Alpha');
        $second = $this->customer($this->salesman($dealer), 'Manager Empty Beta');

        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => '']))
            ->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->actingAs($manager)->from(route('dealer.customers.index'))
            ->get(route('dealer.customers.index', ['status' => 'invalid-status']))
            ->assertRedirect(route('dealer.customers.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_search_never_returns_another_dealers_customer(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $owned = $this->customer($this->salesman($dealer), 'Boundary Manager Owned');
        $other = $this->customer($this->salesman(Dealer::factory()->create()), 'Boundary Manager Other Dealer');

        $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Boundary Manager']))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
    }

    public function test_pagination_is_dealer_scoped_and_preserves_valid_filter_parameters(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesmanA = $this->salesman($dealer);
        $salesmanB = $this->salesman($dealer);
        foreach (range(1, 11) as $number) {
            $this->customer($number % 2 ? $salesmanA : $salesmanB, sprintf('Manager Page Match %02d', $number), status: 'new');
        }
        $hidden = $this->customer($this->salesman(Dealer::factory()->create()), 'Manager Page Match Hidden', status: 'new');

        $response = $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Manager Page', 'status' => 'new']));

        $response->assertOk()->assertDontSee($hidden->name);
        $this->assertCount(10, $response->viewData('customers')->items());
        $response->assertSee('q=Manager%20Page&amp;status=new&amp;page=2', false);
        $pageTwo = $this->actingAs($manager)->get(route('dealer.customers.index', ['q' => 'Manager Page', 'status' => 'new', 'page' => 2]));
        $this->assertCount(1, $pageTwo->viewData('customers')->items());
        $pageTwo->assertDontSee($hidden->name);
    }

    public function test_browser_ownership_and_role_inputs_cannot_broaden_manager_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $otherDealer = Dealer::factory()->create();
        $owned = $this->customer($this->salesman($dealer), 'Manager Injected Owned');
        $otherSalesman = $this->salesman($otherDealer);
        $other = $this->customer($otherSalesman, 'Manager Injected Hidden');

        $this->actingAs($manager)->get(route('dealer.customers.index', [
            'q' => 'Manager Injected',
            'salesman_id' => $otherSalesman->id,
            'role' => UserRole::Admin->value,
        ]))->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get(route('dealer.customers.index', ['dealer_id' => $otherDealer->id]))->assertForbidden();
    }

    public function test_authorization_and_read_only_detail_behavior_remain_unchanged(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $customer = $this->customer($this->salesman($dealer), 'Read Only Filter Customer');

        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))
            ->assertOk()->assertSee('Read only')->assertDontSee('Edit Customer')->assertDontSee('Delete Customer');
        $this->post(route('logout'));
        $this->get(route('dealer.customers.index'))->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('dealer.customers.index'))->assertForbidden();
        }
        $unassigned = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('dealer.customers.index'))->assertForbidden();
    }

    private function manager(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
    }

    private function salesman(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
    }

    private function customer(
        User $salesman,
        string $name,
        string $phone = '01700000000',
        string $email = 'manager.search@example.test',
        string $status = 'new',
    ): Customer {
        return Customer::factory()->create(compact('name', 'phone', 'email', 'status') + ['salesman_id' => $salesman->id]);
    }
}
