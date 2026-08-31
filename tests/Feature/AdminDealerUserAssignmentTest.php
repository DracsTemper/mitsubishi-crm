<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDealerUserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_assign_or_unassign_a_dealer_user(): void
    {
        [$dealer, $user] = $this->dealerAndUser();

        $this->post(route('dealers.users.assign', $dealer), ['user_id' => $user->id])
            ->assertRedirect(route('login'));

        $user->update(['dealer_id' => $dealer->id]);
        $this->delete(route('dealers.users.unassign', [$dealer, $user]))
            ->assertRedirect(route('login'));
        $this->assertSame($dealer->id, $user->fresh()->dealer_id);
    }

    public function test_non_admin_roles_cannot_assign_or_unassign_dealer_users(): void
    {
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            [$dealer, $user] = $this->dealerAndUser();
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)
                ->post(route('dealers.users.assign', $dealer), ['user_id' => $user->id])
                ->assertForbidden();

            $user->update(['dealer_id' => $dealer->id]);
            $this->actingAs($actor)
                ->delete(route('dealers.users.unassign', [$dealer, $user]))
                ->assertForbidden();
            $this->assertSame($dealer->id, $user->fresh()->dealer_id);
        }
    }

    public function test_admin_can_view_and_assign_an_existing_dealer_user(): void
    {
        $admin = $this->admin();
        [$dealer, $user] = $this->dealerAndUser();

        $this->actingAs($admin)->get(route('dealers.show', $dealer))
            ->assertOk()
            ->assertSee('Managers')
            ->assertSee($user->email);

        $this->actingAs($admin)
            ->post(route('dealers.users.assign', $dealer), ['user_id' => $user->id])
            ->assertRedirect(route('dealers.show', $dealer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'dealer_id' => $dealer->id]);
        $this->assertTrue($dealer->fresh()->users->contains($user));
    }

    public function test_admin_can_reassign_a_dealer_user_to_another_dealer(): void
    {
        $admin = $this->admin();
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $first->id]);

        $this->actingAs($admin)
            ->post(route('dealers.users.assign', $second), ['user_id' => $user->id])
            ->assertRedirect(route('dealers.show', $second));

        $this->assertSame($second->id, $user->fresh()->dealer_id);
        $this->assertFalse($first->fresh()->users->contains($user));
        $this->assertTrue($second->fresh()->users->contains($user));
    }

    public function test_admin_can_unassign_a_user_without_deleting_either_record(): void
    {
        $admin = $this->admin();
        [$dealer, $user] = $this->dealerAndUser();
        $user->update(['dealer_id' => $dealer->id]);

        $this->actingAs($admin)
            ->delete(route('dealers.users.unassign', [$dealer, $user]))
            ->assertRedirect(route('dealers.show', $dealer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'dealer_id' => null]);
        $this->assertDatabaseHas('dealers', ['id' => $dealer->id]);
    }

    public function test_admin_cannot_assign_non_dealer_roles(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        foreach ([UserRole::Admin, UserRole::Salesman, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)
                ->from(route('dealers.show', $dealer))
                ->post(route('dealers.users.assign', $dealer), ['user_id' => $user->id])
                ->assertRedirect(route('dealers.show', $dealer))
                ->assertSessionHasErrors('user_id');

            $this->assertNull($user->fresh()->dealer_id);
        }
    }

    public function test_invalid_user_id_is_rejected(): void
    {
        $dealer = Dealer::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('dealers.users.assign', $dealer), ['user_id' => 999999])
            ->assertSessionHasErrors('user_id');
    }

    public function test_unassignment_cannot_target_a_user_assigned_to_another_dealer(): void
    {
        $managedDealer = Dealer::factory()->create();
        $actualDealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::Dealer,
            'dealer_id' => $actualDealer->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('dealers.users.unassign', [$managedDealer, $user]))
            ->assertNotFound();

        $this->assertSame($actualDealer->id, $user->fresh()->dealer_id);
    }

    public function test_authentication_remains_intact_after_assignment(): void
    {
        [$dealer, $user] = $this->dealerAndUser();
        $user->update(['password' => 'valid-password']);

        $this->actingAs($this->admin())
            ->post(route('dealers.users.assign', $dealer), ['user_id' => $user->id]);
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'valid-password',
        ])->assertRedirect(route('dealer.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    /** @return array{Dealer, User} */
    private function dealerAndUser(): array
    {
        return [
            Dealer::factory()->create(),
            User::factory()->create(['role' => UserRole::Dealer]),
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
