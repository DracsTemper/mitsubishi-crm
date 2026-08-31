<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDealerSalesmanAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_assign_an_existing_salesman(): void
    {
        $admin = $this->admin();
        [$dealer, $salesman] = $this->dealerAndSalesman();

        $this->actingAs($admin)->get(route('dealers.show', $dealer))
            ->assertOk()
            ->assertSee('Dealer Salesmen')
            ->assertSee($salesman->email);

        $this->actingAs($admin)
            ->post(route('dealers.salesmen.assign', $dealer), ['user_id' => $salesman->id])
            ->assertRedirect(route('dealers.show', $dealer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $salesman->id,
            'role' => UserRole::Salesman->value,
            'dealer_id' => $dealer->id,
        ]);
        $this->assertTrue($dealer->fresh()->users->contains($salesman));
    }

    public function test_admin_can_reassign_a_salesman_between_dealers(): void
    {
        $admin = $this->admin();
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $first->id,
        ]);

        $this->actingAs($admin)
            ->post(route('dealers.salesmen.assign', $second), ['user_id' => $salesman->id])
            ->assertRedirect(route('dealers.show', $second));

        $this->assertSame($second->id, $salesman->fresh()->dealer_id);
        $this->assertFalse($first->fresh()->users->contains($salesman));
        $this->assertTrue($second->fresh()->users->contains($salesman));
    }

    public function test_admin_can_unassign_a_salesman_without_deleting_records(): void
    {
        $admin = $this->admin();
        [$dealer, $salesman] = $this->dealerAndSalesman();
        $salesman->update(['dealer_id' => $dealer->id]);

        $this->actingAs($admin)
            ->delete(route('dealers.salesmen.unassign', [$dealer, $salesman]))
            ->assertRedirect(route('dealers.show', $dealer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $salesman->id, 'dealer_id' => null]);
        $this->assertDatabaseHas('dealers', ['id' => $dealer->id]);
    }

    public function test_guest_cannot_assign_or_unassign_salesmen(): void
    {
        [$dealer, $salesman] = $this->dealerAndSalesman();

        $this->post(route('dealers.salesmen.assign', $dealer), ['user_id' => $salesman->id])
            ->assertRedirect(route('login'));

        $salesman->update(['dealer_id' => $dealer->id]);
        $this->delete(route('dealers.salesmen.unassign', [$dealer, $salesman]))
            ->assertRedirect(route('login'));
        $this->assertSame($dealer->id, $salesman->fresh()->dealer_id);
    }

    public function test_non_admin_roles_cannot_assign_or_unassign_salesmen(): void
    {
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            [$dealer, $salesman] = $this->dealerAndSalesman();
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)
                ->post(route('dealers.salesmen.assign', $dealer), ['user_id' => $salesman->id])
                ->assertForbidden();

            $salesman->update(['dealer_id' => $dealer->id]);
            $this->actingAs($actor)
                ->delete(route('dealers.salesmen.unassign', [$dealer, $salesman]))
                ->assertForbidden();
            $this->assertSame($dealer->id, $salesman->fresh()->dealer_id);
        }
    }

    public function test_non_salesman_roles_cannot_be_assigned_through_salesman_workflow(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)
                ->from(route('dealers.show', $dealer))
                ->post(route('dealers.salesmen.assign', $dealer), ['user_id' => $user->id])
                ->assertRedirect(route('dealers.show', $dealer))
                ->assertSessionHasErrorsIn('assignSalesman', 'user_id');

            $this->assertNull($user->fresh()->dealer_id);
        }
    }

    public function test_invalid_salesman_id_is_rejected(): void
    {
        $dealer = Dealer::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('dealers.salesmen.assign', $dealer), ['user_id' => 999999])
            ->assertSessionHasErrorsIn('assignSalesman', 'user_id');
    }

    public function test_unassigning_from_wrong_dealer_preserves_real_assignment(): void
    {
        $managedDealer = Dealer::factory()->create();
        $actualDealer = Dealer::factory()->create();
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $actualDealer->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('dealers.salesmen.unassign', [$managedDealer, $salesman]))
            ->assertNotFound();

        $this->assertSame($actualDealer->id, $salesman->fresh()->dealer_id);
    }

    public function test_role_specific_unassignment_routes_cannot_be_crossed(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);
        $dealerUser = User::factory()->create([
            'role' => UserRole::Dealer,
            'dealer_id' => $dealer->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('dealers.users.unassign', [$dealer, $salesman]))
            ->assertNotFound();
        $this->actingAs($this->admin())
            ->delete(route('dealers.salesmen.unassign', [$dealer, $dealerUser]))
            ->assertNotFound();

        $this->assertSame($dealer->id, $salesman->fresh()->dealer_id);
        $this->assertSame($dealer->id, $dealerUser->fresh()->dealer_id);
    }

    /** @return array{Dealer, User} */
    private function dealerAndSalesman(): array
    {
        return [
            Dealer::factory()->create(),
            User::factory()->create(['role' => UserRole::Salesman]),
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
