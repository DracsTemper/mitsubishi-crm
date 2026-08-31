<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerSearchFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_searches_global_customers_by_partial_name_phone_and_email(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->customer($this->salesman(Dealer::factory()->create()), 'Rahim Global Search', '01727111111', 'rahim.admin@example.test');
        $second = $this->customer($this->salesman(Dealer::factory()->create()), 'Nusrat Network Customer', '01827222222', 'nusrat.admin@example.test');

        $this->actingAs($admin)->get(route('customers.index'))->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->get(route('customers.index', ['q' => 'Global Sea']))->assertOk()->assertSee($first->name)->assertDontSee($second->name);
        $this->get(route('customers.index', ['q' => '2722']))->assertOk()->assertSee($second->name)->assertDontSee($first->name);
        $this->get(route('customers.index', ['q' => 'rahim.admin@']))->assertOk()->assertSee($first->name)->assertDontSee($second->name);
    }

    public function test_status_and_combined_filters_apply_to_global_scope(): void
    {
        $admin = $this->user(UserRole::Admin);
        $salesman = $this->salesman(Dealer::factory()->create());
        $hotRahim = $this->customer($salesman, 'Rahim Admin Hot', status: 'hot');
        $newRahim = $this->customer($salesman, 'Rahim Admin New', status: 'new');
        $hotKarim = $this->customer($salesman, 'Karim Admin Hot', status: 'hot');

        $this->actingAs($admin)->get(route('customers.index', ['status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertSee($hotKarim->name)->assertDontSee($newRahim->name);
        $this->get(route('customers.index', ['q' => 'Rahim', 'status' => 'hot']))
            ->assertOk()->assertSee($hotRahim->name)->assertDontSee($newRahim->name)->assertDontSee($hotKarim->name);
    }

    public function test_empty_search_returns_every_customer_and_invalid_status_is_rejected(): void
    {
        $admin = $this->user(UserRole::Admin);
        $first = $this->customer($this->salesman(Dealer::factory()->create()), 'Admin Empty Alpha');
        $second = $this->customer($this->salesman(Dealer::factory()->create()), 'Admin Empty Beta');

        $this->actingAs($admin)->get(route('customers.index', ['q' => '']))
            ->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->from(route('customers.index'))->get(route('customers.index', ['status' => 'invalid']))
            ->assertRedirect(route('customers.index'))->assertSessionHasErrors('status');
    }

    public function test_global_results_include_multiple_dealers_salesmen_and_unassigned_salesman(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealerCustomer = $this->customer($this->salesman(Dealer::factory()->create()), 'Function 27 Global Dealer A');
        $otherCustomer = $this->customer($this->salesman(Dealer::factory()->create()), 'Function 27 Global Dealer B');
        $unassignedCustomer = $this->customer($this->salesman(), 'Function 27 Global Unassigned');

        $this->actingAs($admin)->get(route('customers.index', ['q' => 'Function 27 Global']))
            ->assertOk()->assertSee($dealerCustomer->name)->assertSee($otherCustomer->name)->assertSee($unassignedCustomer->name)
            ->assertSee('No Dealer assigned');
    }

    public function test_pagination_is_global_and_preserves_valid_filter_parameters(): void
    {
        $admin = $this->user(UserRole::Admin);
        foreach (range(1, 11) as $number) {
            $this->customer($this->salesman($number % 2 ? Dealer::factory()->create() : null), sprintf('Admin Page Match %02d', $number), status: 'hot');
        }

        $response = $this->actingAs($admin)->get(route('customers.index', ['q' => 'Admin Page', 'status' => 'hot']));
        $response->assertOk();
        $this->assertCount(10, $response->viewData('customers')->items());
        $response->assertSee('q=Admin%20Page&amp;status=hot&amp;page=2', false);
        $pageTwo = $this->get(route('customers.index', ['q' => 'Admin Page', 'status' => 'hot', 'page' => 2]));
        $this->assertCount(1, $pageTwo->viewData('customers')->items());
    }

    public function test_browser_role_input_is_ignored_and_non_admin_roles_remain_forbidden(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $first = $this->customer($this->salesman($dealerA), 'Admin Tamper First');
        $secondSalesman = $this->salesman($dealerB);
        $second = $this->customer($secondSalesman, 'Admin Tamper Second');

        $this->actingAs($admin)->get(route('customers.index', [
            'q' => 'Admin Tamper', 'role' => UserRole::Customer->value,
        ]))->assertOk()->assertSee($first->name)->assertSee($second->name);

        $this->post(route('logout'));
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $this->actingAs($this->user($role))->get(route('customers.index'))->assertForbidden();
        }
    }

    public function test_existing_admin_create_update_and_delete_behavior_remains_working(): void
    {
        $admin = $this->user(UserRole::Admin);
        $salesman = $this->salesman(Dealer::factory()->create());
        $payload = ['salesman_id' => $salesman->id, 'name' => 'Function 27 CRUD', 'phone' => '01727000000', 'email' => 'function27.crud@example.test', 'status' => 'new'];

        $this->actingAs($admin)->post(route('customers.store'), $payload)->assertRedirect();
        $customer = Customer::query()->where('email', $payload['email'])->firstOrFail();
        $this->put(route('customers.update', $customer), array_merge($payload, ['status' => 'hot']))->assertRedirect(route('customers.show', $customer));
        $this->assertSame('hot', $customer->fresh()->status);
        $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));
        $this->assertModelMissing($customer);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function salesman(?Dealer $dealer = null): User
    {
        return User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer?->id]);
    }

    private function customer(User $salesman, string $name, string $phone = '01700000000', string $email = 'admin.search@example.test', string $status = 'new'): Customer
    {
        return Customer::factory()->create(compact('name', 'phone', 'email', 'status') + ['salesman_id' => $salesman->id]);
    }
}
