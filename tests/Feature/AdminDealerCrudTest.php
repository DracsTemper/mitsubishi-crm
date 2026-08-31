<?php

namespace Tests\Feature;

use App\Enums\DealerStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDealerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_or_mutate_dealer_management(): void
    {
        $dealer = Dealer::factory()->create();

        $this->get(route('dealers.index'))->assertRedirect(route('login'));
        $this->post(route('dealers.store'), $this->validPayload())->assertRedirect(route('login'));
        $this->get(route('dealers.edit', $dealer))->assertRedirect(route('login'));
        $this->put(route('dealers.update', $dealer), $this->validPayload())->assertRedirect(route('login'));
        $this->patch(route('dealers.status', $dealer), ['status' => 'inactive'])
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_cannot_access_dealer_management(): void
    {
        $dealer = Dealer::factory()->create();

        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('dealers.index'))->assertForbidden();
            $this->actingAs($user)->get(route('dealers.show', $dealer))->assertForbidden();
            $this->actingAs($user)->get(route('dealers.create'))->assertForbidden();
            $this->actingAs($user)->post(route('dealers.store'), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->get(route('dealers.edit', $dealer))->assertForbidden();
            $this->actingAs($user)->put(route('dealers.update', $dealer), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->patch(route('dealers.status', $dealer), ['status' => 'inactive'])
                ->assertForbidden();
        }
    }

    public function test_admin_can_list_create_and_view_a_dealer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $existing = Dealer::factory()->create(['name' => 'Mitsubishi Test Existing']);

        $this->actingAs($admin)->get(route('dealers.index'))
            ->assertOk()
            ->assertSee($existing->name);

        $this->actingAs($admin)->get(route('dealers.create'))->assertOk();

        $response = $this->actingAs($admin)->post(route('dealers.store'), $this->validPayload());
        $created = Dealer::query()->where('code', 'DHA-001')->firstOrFail();

        $response->assertRedirect(route('dealers.show', $created));
        $this->assertDatabaseHas('dealers', $this->validPayload());
        $this->actingAs($admin)->get(route('dealers.show', $created))
            ->assertOk()
            ->assertSee('Mitsubishi Test Dhaka')
            ->assertSee('DHA-001');
    }

    public function test_admin_can_edit_a_dealer_and_keep_its_existing_code(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dealer = Dealer::factory()->create(['code' => 'DHA-001']);
        $payload = $this->validPayload([
            'name' => 'Mitsubishi Test Dhaka Updated',
            'code' => 'DHA-001',
            'city' => 'Chattogram',
        ]);

        $this->actingAs($admin)->get(route('dealers.edit', $dealer))
            ->assertOk()
            ->assertSee($dealer->name);

        $this->actingAs($admin)->put(route('dealers.update', $dealer), $payload)
            ->assertRedirect(route('dealers.show', $dealer));

        $this->assertDatabaseHas('dealers', [
            'id' => $dealer->id,
            'name' => 'Mitsubishi Test Dhaka Updated',
            'code' => 'DHA-001',
            'city' => 'Chattogram',
        ]);
    }

    public function test_admin_can_deactivate_and_activate_a_dealer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dealer = Dealer::factory()->create(['status' => DealerStatus::Active]);

        $this->actingAs($admin)->patch(route('dealers.status', $dealer), ['status' => 'inactive'])
            ->assertRedirect(route('dealers.show', $dealer));
        $this->assertSame(DealerStatus::Inactive, $dealer->fresh()->status);

        $this->actingAs($admin)->patch(route('dealers.status', $dealer), ['status' => 'active'])
            ->assertRedirect(route('dealers.show', $dealer));
        $this->assertSame(DealerStatus::Active, $dealer->fresh()->status);
    }

    public function test_required_fields_email_and_status_are_validated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->from(route('dealers.create'))->post(route('dealers.store'), [
            'email' => 'not-an-email',
            'status' => 'pending',
        ]);

        $response->assertRedirect(route('dealers.create'));
        $response->assertSessionHasErrors(['name', 'code', 'email', 'city', 'status']);
        $this->assertDatabaseCount('dealers', 0);
    }

    public function test_duplicate_code_is_rejected_for_create_and_for_another_dealers_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $first = Dealer::factory()->create(['code' => 'DHA-001']);
        $second = Dealer::factory()->create(['code' => 'DHA-002']);

        $this->actingAs($admin)->post(route('dealers.store'), $this->validPayload())
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)->put(
            route('dealers.update', $second),
            $this->validPayload(['code' => $first->code]),
        )->assertSessionHasErrors('code');

        $this->assertSame('DHA-002', $second->fresh()->code);
    }

    public function test_invalid_status_update_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dealer = Dealer::factory()->create(['status' => DealerStatus::Active]);

        $this->actingAs($admin)->patch(route('dealers.status', $dealer), ['status' => 'suspended'])
            ->assertSessionHasErrors('status');

        $this->assertSame(DealerStatus::Active, $dealer->fresh()->status);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mitsubishi Test Dhaka',
            'code' => 'DHA-001',
            'phone' => '01700000000',
            'email' => 'dhaka@example.test',
            'address' => '123 Test Avenue',
            'city' => 'Dhaka',
            'status' => 'active',
        ], $overrides);
    }
}
