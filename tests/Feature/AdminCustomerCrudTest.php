<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_mutate_customer_crud(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->get(route('customers.create'))->assertRedirect(route('login'));
        $this->post(route('customers.store'), $this->validPayload($customer->salesman))
            ->assertRedirect(route('login'));
        $this->get(route('customers.show', $customer))->assertRedirect(route('login'));
        $this->get(route('customers.edit', $customer))->assertRedirect(route('login'));
        $this->put(route('customers.update', $customer), $this->validPayload($customer->salesman))
            ->assertRedirect(route('login'));
        $this->delete(route('customers.destroy', $customer))->assertRedirect(route('login'));
        $this->assertModelExists($customer);
    }

    public function test_non_admin_roles_cannot_access_or_mutate_customer_crud(): void
    {
        $customer = Customer::factory()->create();

        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)->get(route('customers.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('customers.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('customers.store'), $this->validPayload($customer->salesman))->assertForbidden();
            $this->actingAs($actor)->get(route('customers.show', $customer))->assertForbidden();
            $this->actingAs($actor)->get(route('customers.edit', $customer))->assertForbidden();
            $this->actingAs($actor)->put(route('customers.update', $customer), $this->validPayload($customer->salesman))->assertForbidden();
            $this->actingAs($actor)->delete(route('customers.destroy', $customer))->assertForbidden();
        }

        $this->assertModelExists($customer);
    }

    public function test_admin_can_list_create_store_view_and_edit_a_customer(): void
    {
        $admin = $this->admin();
        [$dealer, $salesman] = $this->dealerAndSalesman();

        $this->actingAs($admin)->get(route('customers.index'))->assertOk();
        $this->actingAs($admin)->get(route('customers.create'))
            ->assertOk()
            ->assertSee($salesman->name)
            ->assertSee($dealer->name);

        $response = $this->actingAs($admin)
            ->post(route('customers.store'), $this->validPayload($salesman));
        $customer = Customer::query()->where('email', 'fixture.customer@example.test')->firstOrFail();

        $response->assertRedirect(route('customers.show', $customer))->assertSessionHas('success');
        $this->assertSame($salesman->id, $customer->salesman_id);
        $this->assertTrue($customer->salesman->dealer->is($dealer));

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()->assertSee($customer->name)->assertSee($salesman->name)->assertSee($dealer->name);
        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()->assertSee($customer->name)->assertSee($salesman->name)->assertSee($dealer->name);
        $this->actingAs($admin)->get(route('customers.edit', $customer))
            ->assertOk()->assertSee($customer->name);
    }

    public function test_admin_can_update_status_and_reassign_customer_across_dealers(): void
    {
        $admin = $this->admin();
        [$dealerA, $salesmanA] = $this->dealerAndSalesman();
        [$dealerB, $salesmanB] = $this->dealerAndSalesman();
        $customer = Customer::factory()->create(['salesman_id' => $salesmanA->id]);

        $this->actingAs($admin)->put(
            route('customers.update', $customer),
            $this->validPayload($salesmanB, ['name' => 'Updated Customer', 'status' => 'reserved']),
        )->assertRedirect(route('customers.show', $customer))->assertSessionHas('success');

        $customer->refresh()->load('salesman.dealer');
        $this->assertSame('Updated Customer', $customer->name);
        $this->assertSame('reserved', $customer->status);
        $this->assertSame($salesmanB->id, $customer->salesman_id);
        $this->assertTrue($customer->salesman->dealer->is($dealerB));
        $this->assertFalse($customer->salesman->dealer->is($dealerA));
    }

    public function test_admin_can_delete_only_the_customer(): void
    {
        $admin = $this->admin();
        [$dealer, $salesman] = $this->dealerAndSalesman();
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $otherCustomer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->actingAs($admin)->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))->assertSessionHas('success');

        $this->assertModelMissing($customer);
        $this->assertModelExists($otherCustomer);
        $this->assertModelExists($salesman);
        $this->assertModelExists($dealer);
    }

    public function test_non_salesman_and_missing_users_are_rejected_as_owner(): void
    {
        $admin = $this->admin();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)
                ->post(route('customers.store'), $this->validPayload($user, ['email' => "{$role->value}@example.test"]))
                ->assertSessionHasErrors('salesman_id');
        }

        $this->actingAs($admin)
            ->post(route('customers.store'), $this->validPayload(null, ['salesman_id' => 999999]))
            ->assertSessionHasErrors('salesman_id');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_required_fields_email_and_status_are_validated(): void
    {
        [, $salesman] = $this->dealerAndSalesman();

        $response = $this->actingAs($this->admin())->post(route('customers.store'), [
            'salesman_id' => $salesman->id,
            'email' => 'not-an-email',
            'status' => 'unsupported',
        ]);

        $response->assertSessionHasErrors(['name', 'phone', 'email', 'status']);
        $this->assertDatabaseCount('customers', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /** @return array{Dealer, User} */
    private function dealerAndSalesman(): array
    {
        $dealer = Dealer::factory()->create();

        return [$dealer, User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ])];
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(?User $salesman, array $overrides = []): array
    {
        return array_merge([
            'salesman_id' => $salesman?->id,
            'name' => 'Fixture Customer',
            'phone' => '000-1500-0001',
            'email' => 'fixture.customer@example.test',
            'address' => '15 CRUD Test Road',
            'city' => 'Test City',
            'status' => 'new',
        ], $overrides);
    }
}
