<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSalesmanRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_registration_page_and_select_any_dealer(): void
    {
        $admin = $this->admin();
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();

        $this->actingAs($admin)->get(route('salesmen.create', ['dealer_id' => $second->id]))
            ->assertOk()
            ->assertSee('Create Salesman')
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertSee($first->code)
            ->assertSee($second->code)
            ->assertDontSee('name="role"', false);
    }

    public function test_guest_and_non_admin_roles_cannot_access_or_submit_registration(): void
    {
        $dealer = Dealer::factory()->create();

        $this->get(route('salesmen.create'))->assertRedirect(route('login'));
        $this->post(route('salesmen.store'), $this->validPayload($dealer))->assertRedirect(route('login'));

        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $payload = $this->validPayload($dealer, ['email' => "created-by-{$role->value}@example.test"]);

            $this->actingAs($actor)->get(route('salesmen.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('salesmen.store'), $payload)->assertForbidden();
            $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        }
    }

    public function test_admin_creates_hashed_salesman_for_selected_dealer_and_role_is_server_controlled(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        foreach ([UserRole::Admin, UserRole::Dealer] as $submittedRole) {
            $email = "malicious-{$submittedRole->value}@example.test";
            $response = $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($dealer, [
                'email' => $email,
                'role' => $submittedRole->value,
            ]));
            $salesman = User::query()->where('email', $email)->firstOrFail();

            $response->assertRedirect(route('dealers.show', $dealer))->assertSessionHas('success');
            $this->assertSame(UserRole::Salesman, $salesman->role);
            $this->assertSame($dealer->id, $salesman->dealer_id);
            $this->assertNotSame('AdminSalesmanPassword123!', $salesman->password);
            $this->assertTrue(Hash::check('AdminSalesmanPassword123!', $salesman->password));
        }

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_create_salesmen_for_different_dealers_without_changing_existing_records(): void
    {
        $admin = $this->admin();
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();
        $existingManager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $first->id]);

        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($first, ['email' => 'first.salesman@example.test']));
        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($second, ['email' => 'second.salesman@example.test']));

        $this->assertDatabaseHas('users', ['email' => 'first.salesman@example.test', 'role' => UserRole::Salesman->value, 'dealer_id' => $first->id]);
        $this->assertDatabaseHas('users', ['email' => 'second.salesman@example.test', 'role' => UserRole::Salesman->value, 'dealer_id' => $second->id]);
        $this->assertDatabaseHas('users', ['id' => $existingManager->id, 'role' => UserRole::Dealer->value, 'dealer_id' => $first->id]);
        $this->assertDatabaseHas('dealers', ['id' => $first->id, 'name' => $first->name]);
        $this->assertDatabaseHas('dealers', ['id' => $second->id, 'name' => $second->name]);
    }

    public function test_unique_email_invalid_or_missing_dealer_and_password_confirmation_are_rejected(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($dealer, ['email' => 'duplicate@example.test']))->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($dealer, ['dealer_id' => null]))->assertSessionHasErrors('dealer_id');
        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($dealer, ['dealer_id' => 999999]))->assertSessionHasErrors('dealer_id');
        $this->actingAs($admin)->post(route('salesmen.store'), $this->validPayload($dealer, ['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.admin.salesman@example.test']);
    }

    public function test_created_salesman_authenticates_through_existing_login(): void
    {
        $dealer = Dealer::factory()->create();
        $this->actingAs($this->admin())->post(route('salesmen.store'), $this->validPayload($dealer));
        $salesman = User::query()->where('email', 'new.admin.salesman@example.test')->firstOrFail();
        $this->post(route('logout'));

        $this->post(route('login.submit'), [
            'email' => $salesman->email,
            'password' => 'AdminSalesmanPassword123!',
        ])->assertRedirect(route('salesman.dashboard'));
        $this->assertAuthenticatedAs($salesman);
    }

    public function test_manager_registration_and_manager_salesman_workflows_remain_operational(): void
    {
        $dealer = Dealer::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('managers.store'), [
            'name' => 'Compatibility Manager',
            'email' => 'compat.manager@example.test',
            'password' => 'CompatibilityPassword123!',
            'password_confirmation' => 'CompatibilityPassword123!',
            'dealer_id' => $dealer->id,
        ]);
        $manager = User::query()->where('email', 'compat.manager@example.test')->firstOrFail();

        $this->actingAs($manager)->post(route('dealer.salesmen.store'), [
            'name' => 'Compatibility Salesman',
            'email' => 'compat.salesman@example.test',
            'password' => 'CompatibilityPassword123!',
            'password_confirmation' => 'CompatibilityPassword123!',
        ]);
        $salesman = User::query()->where('email', 'compat.salesman@example.test')->firstOrFail();

        $this->actingAs($manager)->get(route('dealer.salesmen.index'))->assertOk()->assertSee($salesman->name);
        $this->actingAs($manager)->get(route('dealer.salesmen.show', $salesman))->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(Dealer $dealer, array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Admin Salesman',
            'email' => 'new.admin.salesman@example.test',
            'password' => 'AdminSalesmanPassword123!',
            'password_confirmation' => 'AdminSalesmanPassword123!',
            'dealer_id' => $dealer->id,
        ], $overrides);
    }
}
