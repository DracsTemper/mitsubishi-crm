<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerSalesmanRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_manager_can_access_registration_form_without_ownership_controls(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);

        $this->actingAs($manager)->get(route('dealer.salesmen.create'))
            ->assertOk()
            ->assertSee('Create Salesman')
            ->assertSee($dealer->name)
            ->assertDontSee('name="dealer_id"', false)
            ->assertDontSee('name="role"', false);
    }

    public function test_manager_creates_hashed_salesman_for_own_dealer_and_browser_ownership_fields_are_ignored(): void
    {
        $dealer = Dealer::factory()->create();
        $otherDealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);

        $response = $this->actingAs($manager)->post(route('dealer.salesmen.store'), [
            ...$this->validPayload(),
            'dealer_id' => $otherDealer->id,
            'role' => UserRole::Admin->value,
        ]);
        $salesman = User::query()->where('email', 'new.salesman@example.test')->firstOrFail();

        $response->assertRedirect(route('dealer.team'))->assertSessionHas('success');
        $this->assertSame(UserRole::Salesman, $salesman->role);
        $this->assertSame($dealer->id, $salesman->dealer_id);
        $this->assertTrue($salesman->dealer->is($dealer));
        $this->assertNotSame('SalesmanPassword123!', $salesman->password);
        $this->assertTrue(Hash::check('SalesmanPassword123!', $salesman->password));
        $this->assertAuthenticatedAs($manager);
    }

    public function test_guest_and_non_manager_roles_cannot_access_or_submit_registration(): void
    {
        $dealer = Dealer::factory()->create();

        $this->get(route('dealer.salesmen.create'))->assertRedirect(route('login'));
        $this->post(route('dealer.salesmen.store'), $this->validPayload())->assertRedirect(route('login'));

        foreach ([UserRole::Admin, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role, 'dealer_id' => $role === UserRole::Salesman ? $dealer->id : null]);
            $payload = $this->validPayload(['email' => "created-by-{$role->value}@example.test"]);

            $this->actingAs($actor)->get(route('dealer.salesmen.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('dealer.salesmen.store'), $payload)->assertForbidden();
            $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        }
    }

    public function test_unassigned_manager_cannot_access_or_submit_registration(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => null]);

        $this->actingAs($manager)->get(route('dealer.salesmen.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('dealer.salesmen.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new.salesman@example.test']);
    }

    public function test_unique_email_password_confirmation_and_required_fields_are_validated(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->actingAs($manager)->post(route('dealer.salesmen.store'), $this->validPayload([
            'email' => 'duplicate@example.test',
        ]))->assertSessionHasErrors('email');

        $this->actingAs($manager)->post(route('dealer.salesmen.store'), $this->validPayload([
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->actingAs($manager)->post(route('dealer.salesmen.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseMissing('users', ['email' => 'new.salesman@example.test']);
    }

    public function test_created_salesman_can_authenticate_through_shared_login(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $this->actingAs($manager)->post(route('dealer.salesmen.store'), $this->validPayload());
        $salesman = User::query()->where('email', 'new.salesman@example.test')->firstOrFail();
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $salesman->email,
            'password' => 'SalesmanPassword123!',
        ])->assertRedirect(route('salesman.dashboard'));
        $this->assertAuthenticatedAs($salesman);
    }

    public function test_team_page_lists_only_salesmen_from_authenticated_managers_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = $this->manager($dealer);
        $existingManager = $this->manager($dealer);
        $ownedSalesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $otherSalesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => Dealer::factory()->create()->id]);

        $this->actingAs($manager)->get(route('dealer.team'))
            ->assertOk()
            ->assertSee($ownedSalesman->name)
            ->assertDontSee($otherSalesman->name)
            ->assertDontSee($existingManager->email)
            ->assertSee('Create Salesman');

        $this->assertDatabaseHas('users', ['id' => $existingManager->id, 'role' => UserRole::Dealer->value]);
        $this->assertDatabaseHas('users', ['id' => $otherSalesman->id, 'dealer_id' => $otherSalesman->dealer_id]);
    }

    private function manager(Dealer $dealer): User
    {
        return User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Fixture Salesman',
            'email' => 'new.salesman@example.test',
            'password' => 'SalesmanPassword123!',
            'password_confirmation' => 'SalesmanPassword123!',
        ], $overrides);
    }
}
