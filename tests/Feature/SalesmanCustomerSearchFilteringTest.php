<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanCustomerSearchFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesman_can_search_owned_customers_by_partial_name_phone_and_email(): void
    {
        [, $salesman] = $this->assignedSalesman();
        $rahim = $this->customer($salesman, 'Rahim Searchable', '01711112222', 'rahim.unique@example.test');
        $nusrat = $this->customer($salesman, 'Nusrat Customer', '01833334444', 'nusrat.filter@example.test');

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'Rahim']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'Search']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => '3333']))
            ->assertOk()->assertSee($nusrat->name)->assertDontSee($rahim->name);
        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'unique@example']))
            ->assertOk()->assertSee($rahim->name)->assertDontSee($nusrat->name);
    }

    public function test_status_filter_and_combined_search_return_only_matching_owned_customers(): void
    {
        [, $salesman] = $this->assignedSalesman();
        $hotRahim = $this->customer($salesman, 'Rahim Hot Match', '01710000001', 'hot.rahim@example.test', 'hot');
        $newRahim = $this->customer($salesman, 'Rahim New Match', '01710000002', 'new.rahim@example.test', 'new');
        $hotOther = $this->customer($salesman, 'Karim Hot Customer', '01710000003', 'hot.karim@example.test', 'hot');

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertSee($hotOther->name)->assertDontSee($newRahim->name);
        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'Rahim', 'status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertDontSee($newRahim->name)->assertDontSee($hotOther->name);
    }

    public function test_empty_search_returns_all_owned_customers_and_invalid_status_is_rejected(): void
    {
        [, $salesman] = $this->assignedSalesman();
        $first = $this->customer($salesman, 'Empty Search Alpha');
        $second = $this->customer($salesman, 'Empty Search Beta');

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => '']))
            ->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->actingAs($salesman)->from(route('salesman.customers.index'))
            ->get(route('salesman.customers.index', ['status' => 'not-supported']))
            ->assertRedirect(route('salesman.customers.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_search_never_returns_same_dealer_colleague_or_other_dealer_customers(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();
        [, $colleague] = $this->assignedSalesman($dealer);
        [, $otherDealerSalesman] = $this->assignedSalesman();
        $owned = $this->customer($salesman, 'Boundary Needle Owned');
        $colleagueCustomer = $this->customer($colleague, 'Boundary Needle Colleague');
        $otherCustomer = $this->customer($otherDealerSalesman, 'Boundary Needle Other Dealer');

        $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'Boundary Needle']))
            ->assertOk()
            ->assertSee($owned->name)
            ->assertDontSee($colleagueCustomer->name)
            ->assertDontSee($otherCustomer->name);
    }

    public function test_pagination_remains_owner_scoped_and_preserves_search_and_status_parameters(): void
    {
        [, $salesman] = $this->assignedSalesman();
        [, $otherSalesman] = $this->assignedSalesman();
        foreach (range(1, 11) as $number) {
            $this->customer($salesman, sprintf('Page Match %02d', $number), status: 'new');
        }
        $hidden = $this->customer($otherSalesman, 'Page Match Hidden Other Owner', status: 'new');

        $response = $this->actingAs($salesman)->get(route('salesman.customers.index', [
            'q' => 'Page Match',
            'status' => 'new',
        ]));

        $response->assertOk()->assertDontSee($hidden->name);
        $this->assertCount(10, $response->viewData('customers')->items());
        $response->assertSee('q=Page%20Match&amp;status=new&amp;page=2', false);

        $pageTwo = $this->actingAs($salesman)->get(route('salesman.customers.index', ['q' => 'Page Match', 'status' => 'new', 'page' => 2]));
        $this->assertCount(1, $pageTwo->viewData('customers')->items());
        $pageTwo->assertDontSee($hidden->name);
    }

    public function test_browser_ownership_inputs_cannot_broaden_results(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();
        [$otherDealer, $otherSalesman] = $this->assignedSalesman();
        $owned = $this->customer($salesman, 'Injected Scope Owned');
        $other = $this->customer($otherSalesman, 'Injected Scope Hidden');

        $this->actingAs($salesman)->get(route('salesman.customers.index', [
            'q' => 'Injected Scope',
            'salesman_id' => $otherSalesman->id,
            'role' => UserRole::Admin->value,
        ]))->assertOk()->assertSee($owned->name)->assertDontSee($other->name);

        $this->actingAs($salesman)->get(route('salesman.customers.index', [
            'dealer_id' => $otherDealer->id,
        ]))->assertForbidden();
        $this->assertNotSame($dealer->id, $otherDealer->id);
    }

    public function test_guest_other_roles_and_unassigned_salesman_keep_existing_authorization(): void
    {
        $this->get(route('salesman.customers.index'))->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('salesman.customers.index'))->assertForbidden();
        }

        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('salesman.customers.index'))->assertForbidden();
    }

    /** @return array{Dealer, User} */
    private function assignedSalesman(?Dealer $dealer = null): array
    {
        $dealer ??= Dealer::factory()->create();

        return [$dealer, User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id])];
    }

    private function customer(
        User $salesman,
        string $name,
        string $phone = '01700000000',
        string $email = 'search.customer@example.test',
        string $status = 'new',
    ): Customer {
        return Customer::factory()->create(compact('name', 'phone', 'email', 'status') + [
            'salesman_id' => $salesman->id,
        ]);
    }
}
