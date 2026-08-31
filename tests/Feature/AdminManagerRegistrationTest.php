<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_manager_creation_page(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        $this->actingAs($admin)->get(route('managers.create', ['dealer_id' => $dealer->id]))
            ->assertOk()
            ->assertSee('Create Manager')
            ->assertSee($dealer->name)
            ->assertSee($dealer->code);
    }

    public function test_guest_cannot_access_or_submit_manager_registration(): void
    {
        $dealer = Dealer::factory()->create();

        $this->get(route('managers.create'))->assertRedirect(route('login'));
        $this->post(route('managers.store'), $this->validPayload($dealer))->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_admin_roles_cannot_create_managers(): void
    {
        $dealer = Dealer::factory()->create();

        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)->get(route('managers.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('managers.store'), $this->validPayload(
                $dealer,
                ['email' => "created-by-{$role->value}@example.test"],
            ))->assertForbidden();
        }

        $this->assertDatabaseCount('users', 3);
    }

    public function test_admin_creates_hashed_manager_account_for_selected_dealer_and_role_is_server_controlled(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        $response = $this->actingAs($admin)->post(route('managers.store'), [
            ...$this->validPayload($dealer),
            'role' => UserRole::Salesman->value,
        ]);
        $manager = User::query()->where('email', 'new.manager@example.test')->firstOrFail();

        $response->assertRedirect(route('dealers.show', $dealer))->assertSessionHas('success');
        $this->assertSame(UserRole::Dealer, $manager->role);
        $this->assertSame($dealer->id, $manager->dealer_id);
        $this->assertTrue($manager->dealer->is($dealer));
        $this->assertNotSame('ManagerPassword123!', $manager->password);
        $this->assertTrue(Hash::check('ManagerPassword123!', $manager->password));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_created_manager_can_authenticate_through_shared_login(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        $this->actingAs($admin)->post(route('managers.store'), $this->validPayload($dealer));
        $manager = User::query()->where('email', 'new.manager@example.test')->firstOrFail();
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $manager->email,
            'password' => 'ManagerPassword123!',
        ])->assertRedirect(route('dealer.dashboard'));
        $this->assertAuthenticatedAs($manager);
    }

    public function test_duplicate_email_missing_or_invalid_dealer_and_password_mismatch_are_rejected(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->actingAs($admin)->post(route('managers.store'), $this->validPayload(
            $dealer,
            ['email' => 'duplicate@example.test'],
        ))->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('managers.store'), $this->validPayload(
            $dealer,
            ['dealer_id' => null],
        ))->assertSessionHasErrors('dealer_id');

        $this->actingAs($admin)->post(route('managers.store'), $this->validPayload(
            $dealer,
            ['dealer_id' => 999999],
        ))->assertSessionHasErrors('dealer_id');

        $this->actingAs($admin)->post(route('managers.store'), $this->validPayload(
            $dealer,
            ['password_confirmation' => 'different-password'],
        ))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'new.manager@example.test']);
    }

    public function test_required_fields_are_validated_without_creating_partial_manager(): void
    {
        $response = $this->actingAs($this->admin())->post(route('managers.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'dealer_id']);
        $this->assertDatabaseCount('users', 1);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(Dealer $dealer, array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Fixture Manager',
            'email' => 'new.manager@example.test',
            'password' => 'ManagerPassword123!',
            'password_confirmation' => 'ManagerPassword123!',
            'dealer_id' => $dealer->id,
        ], $overrides);
    }
}
