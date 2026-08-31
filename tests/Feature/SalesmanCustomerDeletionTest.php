<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanCustomerDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_salesman_can_delete_owned_customer_with_redirect_and_feedback(): void
    {
        [$dealer, $salesman, $customer] = $this->ownedCustomer('Customer To Delete');
        $preserved = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => 'Preserved Customer']);

        $response = $this->actingAs($salesman)->delete(route('salesman.customers.destroy', $customer));

        $response->assertRedirect(route('salesman.customers.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $preserved->id]);
        $this->assertDatabaseHas('users', ['id' => $salesman->id]);
        $this->assertDatabaseHas('dealers', ['id' => $dealer->id]);
        $this->actingAs($salesman)->get(route('salesman.customers.index'))
            ->assertOk()->assertDontSee($customer->name)->assertSee($preserved->name)->assertSee('Customer deleted successfully.');
        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))->assertNotFound();
        $this->actingAs($salesman)->delete(route('salesman.customers.destroy', $customer))->assertNotFound();
    }

    public function test_same_dealer_and_cross_dealer_salesmen_cannot_delete_customer(): void
    {
        [$dealer, $owner, $customer] = $this->ownedCustomer('Protected Customer');
        [, $colleague] = $this->ownedCustomer('Colleague Customer', $dealer);
        [, $otherDealerSalesman] = $this->ownedCustomer('Other Dealer Customer');

        foreach ([$colleague, $otherDealerSalesman] as $attacker) {
            $this->actingAs($attacker)->delete(route('salesman.customers.destroy', $customer), [
                'salesman_id' => $attacker->id,
                'dealer_id' => $attacker->dealer_id,
                'role' => UserRole::Admin->value,
            ])->assertNotFound();
            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'salesman_id' => $owner->id]);
        }
    }

    public function test_malicious_ownership_fields_do_not_prevent_owner_scoped_deletion_or_affect_other_records(): void
    {
        [$dealer, $owner, $customer] = $this->ownedCustomer('Tamper Delete Customer');
        [, $otherSalesman, $otherCustomer] = $this->ownedCustomer('Untouched Customer');

        $this->actingAs($owner)->delete(route('salesman.customers.destroy', $customer), [
            'salesman_id' => $otherSalesman->id,
            'dealer_id' => $otherSalesman->dealer_id,
            'role' => UserRole::Admin->value,
        ])->assertRedirect(route('salesman.customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $otherCustomer->id, 'salesman_id' => $otherSalesman->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'dealer_id' => $dealer->id]);
        $this->assertDatabaseHas('users', ['id' => $otherSalesman->id]);
    }

    public function test_guest_other_roles_and_unassigned_salesman_are_denied(): void
    {
        [$dealer, , $customer] = $this->ownedCustomer('Role Protected Customer');

        $this->delete(route('salesman.customers.destroy', $customer))->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role, 'dealer_id' => $role === UserRole::Dealer ? $dealer->id : null]);
            $this->actingAs($actor)->delete(route('salesman.customers.destroy', $customer))->assertForbidden();
            $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        }

        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->delete(route('salesman.customers.destroy', $customer))->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_owned_detail_displays_csrf_protected_delete_action(): void
    {
        [, $salesman, $customer] = $this->ownedCustomer('Delete UI Customer');

        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))
            ->assertOk()
            ->assertSee('Delete Customer')
            ->assertSee(route('salesman.customers.destroy', $customer), false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_admin_deletion_manager_read_creation_and_editing_behaviors_remain_unchanged(): void
    {
        [$dealer, $salesman, $customer] = $this->ownedCustomer('Compatibility Customer');
        $adminCustomer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);

        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk();
        $this->actingAs($salesman)->get(route('salesman.customers.edit', $customer))->assertOk();
        $this->actingAs($salesman)->post(route('salesman.customers.store'), [
            'name' => 'Function 24 Creation Check',
            'phone' => '+8801700000024',
            'status' => 'new',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('customers.destroy', $adminCustomer))->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $adminCustomer->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    /** @return array{Dealer, User, Customer} */
    private function ownedCustomer(string $name, ?Dealer $dealer = null): array
    {
        $dealer ??= Dealer::factory()->create();
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id, 'name' => $name]);

        return [$dealer, $salesman, $customer];
    }
}
