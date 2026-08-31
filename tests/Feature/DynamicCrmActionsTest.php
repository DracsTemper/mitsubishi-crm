<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicCrmActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manager_assignment_and_unassignment_return_consistent_json(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $manager = $this->user(UserRole::Dealer);

        $this->actingAs($admin)->postJson(route('dealers.users.assign', $dealer), ['user_id' => $manager->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.refresh_url', route('dealers.show', $dealer));
        $this->assertSame($dealer->id, $manager->fresh()->dealer_id);

        $this->deleteJson(route('dealers.users.unassign', [$dealer, $manager]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertNull($manager->fresh()->dealer_id);
    }

    public function test_assignment_validation_authorization_and_not_found_statuses_remain_correct(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $customerRole = $this->user(UserRole::Customer);

        $this->actingAs($admin)->postJson(route('dealers.users.assign', $dealer), ['user_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->actingAs($customerRole)->postJson(route('dealers.users.assign', $dealer), ['user_id' => $customerRole->id])
            ->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('dealers.users.unassign', [$dealer, $customerRole]))
            ->assertNotFound();
    }

    public function test_admin_customer_and_vehicle_dynamic_deletion_preserve_http_semantics(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id]);

        $this->actingAs($admin)->deleteJson(route('customers.destroy', $customer), ['role' => UserRole::Admin->value])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.id', $customer->id);
        $this->deleteJson(route('customers.destroy', $customer))->assertNotFound();
        $this->deleteJson(route('vehicles.destroy', $vehicle))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.id', $vehicle->id);
        $this->deleteJson(route('vehicles.destroy', $vehicle))->assertNotFound();
    }

    public function test_salesman_can_dynamically_delete_only_personally_owned_customer(): void
    {
        $dealer = Dealer::factory()->create();
        $owner = $this->salesman($dealer);
        $colleague = $this->salesman($dealer);
        $owned = Customer::factory()->create(['salesman_id' => $owner->id]);
        $other = Customer::factory()->create(['salesman_id' => $colleague->id]);

        $this->actingAs($owner)->deleteJson(route('salesman.customers.destroy', $other), [
            'salesman_id' => $colleague->id,
            'dealer_id' => $dealer->id,
            'role' => UserRole::Admin->value,
        ])->assertNotFound();
        $this->assertModelExists($other);
        $this->deleteJson(route('salesman.customers.destroy', $owned))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertModelMissing($owned);
    }

    public function test_non_admin_roles_cannot_use_admin_dynamic_delete_endpoints(): void
    {
        $dealer = Dealer::factory()->create();
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id]);
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $this->actingAs($this->user($role))->deleteJson(route('vehicles.destroy', $vehicle), ['role' => UserRole::Admin->value])
                ->assertForbidden();
        }
        $this->assertModelExists($vehicle);
    }

    public function test_non_ajax_fallback_forms_still_redirect_and_change_database_state(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $manager = $this->user(UserRole::Dealer);

        $this->actingAs($admin)->post(route('dealers.users.assign', $dealer), ['user_id' => $manager->id])
            ->assertRedirect(route('dealers.show', $dealer))->assertSessionHas('success');
        $this->assertSame($dealer->id, $manager->fresh()->dealer_id);
        $this->delete(route('dealers.users.unassign', [$dealer, $manager]))
            ->assertRedirect(route('dealers.show', $dealer))->assertSessionHas('success');
        $this->assertNull($manager->fresh()->dealer_id);
    }

    public function test_converted_views_expose_dynamic_hooks_and_csrf_meta(): void
    {
        $admin = $this->user(UserRole::Admin);
        $dealer = Dealer::factory()->create();
        $manager = $this->user(UserRole::Dealer);
        $manager->update(['dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $this->salesman($dealer)->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id]);

        $this->actingAs($admin)->get(route('dealers.show', $dealer))
            ->assertOk()->assertSee('data-crm-ajax', false)->assertSee('data-assignment-panel="managers"', false);
        $this->get(route('customers.show', $customer))
            ->assertOk()->assertSee('data-remove-record="true"', false)->assertSee('name="csrf-token"', false);
        $this->get(route('vehicles.show', $vehicle))
            ->assertOk()->assertSee('data-confirm-title="Delete Vehicle?"', false)->assertDontSee('return confirm(', false);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function salesman(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
    }
}
