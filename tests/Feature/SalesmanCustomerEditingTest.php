<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesmanCustomerEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_salesman_can_open_edit_form_for_owned_customer(): void
    {
        [$dealer, $salesman, $customer] = $this->ownedCustomer();

        $this->actingAs($salesman)->get(route('salesman.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Edit Customer')
            ->assertSee($customer->name)
            ->assertSee($customer->phone)
            ->assertSee($salesman->name)
            ->assertSee($dealer->name)
            ->assertDontSee('name="salesman_id"', false)
            ->assertDontSee('name="dealer_id"', false);
    }

    public function test_salesman_can_update_owned_customer_and_receives_success_feedback(): void
    {
        [, $salesman, $customer] = $this->ownedCustomer();
        $payload = $this->validPayload([
            'name' => 'Updated Owned Customer',
            'phone' => '+8801800000023',
            'status' => 'contacted',
        ]);

        $response = $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $payload);

        $response->assertRedirect(route('salesman.customers.show', $customer))->assertSessionHas('success');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'salesman_id' => $salesman->id,
            'name' => 'Updated Owned Customer',
            'phone' => '+8801800000023',
            'status' => 'contacted',
        ]);
        $this->actingAs($salesman)->get(route('salesman.customers.show', $customer))
            ->assertOk()->assertSee('Customer updated successfully.');
    }

    public function test_malicious_ownership_and_role_fields_cannot_change_customer_owner(): void
    {
        [$dealer, $owner, $customer] = $this->ownedCustomer();
        [, $colleague] = $this->ownedCustomer($dealer);
        [$otherDealer, $otherSalesman] = $this->ownedCustomer();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Salesman] as $role) {
            $this->actingAs($owner)->patch(route('salesman.customers.update', $customer), $this->validPayload([
                'name' => "Tamper {$role->value}",
                'salesman_id' => $role === UserRole::Salesman ? $colleague->id : $otherSalesman->id,
                'dealer_id' => $otherDealer->id,
                'role' => $role->value,
            ]))->assertRedirect(route('salesman.customers.show', $customer));

            $this->assertSame($owner->id, $customer->fresh()->salesman_id);
            $this->assertTrue($customer->fresh()->salesman->dealer->is($dealer));
        }
    }

    public function test_same_dealer_and_cross_dealer_salesmen_cannot_edit_or_update_customer(): void
    {
        [$dealer, $owner, $customer] = $this->ownedCustomer();
        [, $colleague] = $this->ownedCustomer($dealer);
        [, $otherDealerSalesman] = $this->ownedCustomer();
        $original = $customer->only(['name', 'phone', 'email', 'address', 'city', 'status', 'salesman_id']);

        foreach ([$colleague, $otherDealerSalesman] as $attacker) {
            $this->actingAs($attacker)->get(route('salesman.customers.edit', $customer))->assertNotFound();
            $this->actingAs($attacker)->put(route('salesman.customers.update', $customer), $this->validPayload([
                'name' => 'Unauthorized Update',
                'salesman_id' => $attacker->id,
            ]))->assertNotFound();
        }

        $this->assertSame($original, $customer->fresh()->only(array_keys($original)));
        $this->assertSame($owner->id, $customer->fresh()->salesman_id);
    }

    public function test_guest_other_roles_and_unassigned_salesman_are_denied(): void
    {
        [$dealer, , $customer] = $this->ownedCustomer();

        $this->get(route('salesman.customers.edit', $customer))->assertRedirect(route('login'));
        $this->put(route('salesman.customers.update', $customer), $this->validPayload())->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role, 'dealer_id' => $role === UserRole::Dealer ? $dealer->id : null]);
            $this->actingAs($actor)->get(route('salesman.customers.edit', $customer))->assertForbidden();
            $this->actingAs($actor)->put(route('salesman.customers.update', $customer), $this->validPayload())->assertForbidden();
        }

        $unassigned = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('salesman.customers.edit', $customer))->assertForbidden();
        $this->actingAs($unassigned)->put(route('salesman.customers.update', $customer), $this->validPayload())->assertForbidden();
    }

    public function test_update_validation_rejects_invalid_values_and_allows_nullable_fields_to_be_cleared(): void
    {
        [, $salesman, $customer] = $this->ownedCustomer();

        $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $this->validPayload(['name' => null]))->assertSessionHasErrors('name');
        $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $this->validPayload(['phone' => null]))->assertSessionHasErrors('phone');
        $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $this->validPayload(['email' => 'invalid-email']))->assertSessionHasErrors('email');
        $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $this->validPayload(['status' => 'unsupported']))->assertSessionHasErrors('status');

        $this->actingAs($salesman)->put(route('salesman.customers.update', $customer), $this->validPayload([
            'email' => null,
            'address' => null,
            'city' => null,
        ]))->assertRedirect(route('salesman.customers.show', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'email' => null, 'address' => null, 'city' => null]);
    }

    public function test_admin_editing_manager_read_and_customer_creation_behaviors_remain_unchanged(): void
    {
        [$dealer, $salesman, $customer] = $this->ownedCustomer();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);

        $this->actingAs($admin)->get(route('customers.edit', $customer))->assertOk();
        $this->actingAs($manager)->get(route('dealer.customers.show', $customer))->assertOk();
        $this->actingAs($salesman)->post(route('salesman.customers.store'), [
            'name' => 'Compatibility Customer',
            'phone' => '+8801900000023',
            'email' => 'compatibility23@example.test',
            'status' => 'new',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('customers', ['email' => 'compatibility23@example.test', 'salesman_id' => $salesman->id]);
    }

    /** @return array{Dealer, User, Customer} */
    private function ownedCustomer(?Dealer $dealer = null): array
    {
        $dealer ??= Dealer::factory()->create();
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        return [$dealer, $salesman, $customer];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Edited Customer',
            'phone' => '+8801700000023',
            'email' => 'edited.customer@example.test',
            'address' => '23 Edit Road',
            'city' => 'Dhaka',
            'status' => 'active',
        ], $overrides);
    }
}
