<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerSalesmanReadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_list_contains_only_salesmen_from_authenticated_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $otherDealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $ownedA = $this->salesman($dealer, 'Owned Salesman Alpha');
        $ownedB = $this->salesman($dealer, 'Owned Salesman Beta');
        $other = $this->salesman($otherDealer, 'Other Dealer Salesman');
        $unassigned = User::factory()->create(['name' => 'Unassigned Salesman', 'role' => UserRole::Salesman, 'dealer_id' => null]);

        $this->actingAs($manager)->get(route('dealer.salesmen.index'))
            ->assertOk()
            ->assertSee($ownedA->name)
            ->assertSee($ownedB->name)
            ->assertDontSee($other->name)
            ->assertDontSee($unassigned->name);
    }

    public function test_manager_list_excludes_every_non_salesman_role_even_with_same_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesman = $this->salesman($dealer, 'Visible Salesman');
        $admin = User::factory()->create(['name' => 'Hidden Admin', 'role' => UserRole::Admin, 'dealer_id' => $dealer->id]);
        $otherManager = User::factory()->create(['name' => 'Hidden Manager', 'role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $customerUser = User::factory()->create(['name' => 'Hidden Customer User', 'role' => UserRole::Customer, 'dealer_id' => $dealer->id]);

        $this->actingAs($manager)->get(route('dealer.salesmen.index'))
            ->assertOk()
            ->assertSee($salesman->name)
            ->assertDontSee($admin->name)
            ->assertDontSee($otherManager->name)
            ->assertDontSee($customerUser->name);
    }

    public function test_manager_can_view_same_dealer_salesman_detail_with_customer_count(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $salesman = $this->salesman($dealer, 'Scoped Detail Salesman');
        Customer::factory()->count(2)->create(['salesman_id' => $salesman->id]);

        $this->actingAs($manager)->get(route('dealer.salesmen.show', $salesman))
            ->assertOk()
            ->assertSee($salesman->name)
            ->assertSee($salesman->email)
            ->assertSee($dealer->name)
            ->assertSee('Read only')
            ->assertSee('2');
    }

    public function test_cross_dealer_unassigned_and_non_salesman_details_return_not_found(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $other = $this->salesman(Dealer::factory()->create(), 'Cross Dealer Salesman');
        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $sameDealerManager = $this->manager($dealer);

        $this->actingAs($manager)->get(route('dealer.salesmen.show', $other))->assertNotFound();
        $this->actingAs($manager)->get(route('dealer.salesmen.show', $unassigned))->assertNotFound();
        $this->actingAs($manager)->get(route('dealer.salesmen.show', $sameDealerManager))->assertNotFound();
    }

    public function test_guest_non_manager_roles_and_unassigned_manager_are_denied(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer, 'Protected Salesman');

        $this->get(route('dealer.salesmen.index'))->assertRedirect(route('login'));
        $this->get(route('dealer.salesmen.show', $salesman))->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('dealer.salesmen.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('dealer.salesmen.show', $salesman))->assertForbidden();
        }

        $unassignedManager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => null]);
        $this->actingAs($unassignedManager)->get(route('dealer.salesmen.index'))->assertForbidden();
        $this->actingAs($unassignedManager)->get(route('dealer.salesmen.show', $salesman))->assertForbidden();
    }

    public function test_browser_inputs_cannot_broaden_list_or_detail_scope(): void
    {
        $dealer = Dealer::factory()->create();
        $otherDealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $owned = $this->salesman($dealer, 'Authenticated Dealer Salesman');
        $other = $this->salesman($otherDealer, 'Injected Salesman');

        $this->actingAs($manager)->get(route('dealer.salesmen.index', ['role' => 'admin', 'salesman_id' => $other->id]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->actingAs($manager)->get(route('dealer.salesmen.index', ['dealer_id' => $otherDealer->id]))
            ->assertForbidden();
        $this->actingAs($manager)->get(route('dealer.salesmen.show', [$other, 'role' => 'salesman', 'salesman_id' => $owned->id]))
            ->assertNotFound();
    }

    public function test_existing_registration_and_customer_read_scopes_remain_operational(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $existingSalesman = $this->salesman($dealer, 'Existing Salesman');
        $customer = Customer::factory()->create(['salesman_id' => $existingSalesman->id]);

        $this->actingAs($manager)->post(route('dealer.salesmen.store'), [
            'name' => 'Registered Salesman',
            'email' => 'registered.salesman@example.test',
            'password' => 'SalesmanPassword123!',
            'password_confirmation' => 'SalesmanPassword123!',
        ])->assertRedirect(route('dealer.team'));
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk();

        $this->actingAs($existingSalesman)->get(route('salesman.customers.show', $customer))->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'registered.salesman@example.test',
            'role' => UserRole::Salesman->value,
            'dealer_id' => $dealer->id,
        ]);
    }

    private function manager(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
    }

    private function salesman(Dealer $dealer, string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
    }
}
