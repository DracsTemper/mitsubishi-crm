<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrototypePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_prototype_pages_render_successfully(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);
        Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->assertPagesRender(UserRole::Admin, [
            '/admin/dashboard', '/customers', '/customers/1', '/conversations',
            '/bookings', '/bookings/124', '/calendar', '/inventory', '/reports',
            '/integrations', '/settings', '/dealers', '/dealers/'.$dealer->id, '/salesmen',
            '/salesmen/arif-hasan', '/vehicles', '/test-drives', '/deliveries',
        ]);

        $this->assertPagesRender(UserRole::Dealer, [
            '/dealer/dashboard', '/dealer/customers', '/dealer/customers/{customer}',
            '/dealer/conversations', '/dealer/bookings', '/dealer/calendar',
            '/dealer/inventory', '/dealer/performance', '/dealer/profile', '/dealer/team',
            '/dealer/team/arif-hasan', '/dealer/test-drives', '/dealer/chassis',
            '/dealer/deliveries', '/dealer/reports',
        ]);

        $this->assertPagesRender(UserRole::Salesman, [
            '/salesman/dashboard', '/salesman/customers',
            '/salesman/customers/{customer}', '/salesman/conversations',
            '/salesman/test-drives', '/salesman/calendar', '/salesman/bookings',
            '/salesman/chassis', '/salesman/deliveries',
        ]);
    }

    public function test_all_brand_and_vehicle_assets_exist(): void
    {
        foreach ([
            'assets/images/brand/honda-emblem-transparent.png',
            'assets/images/vehicles/outlander-2026.png',
            'assets/images/vehicles/xforce-2026.png',
            'assets/images/vehicles/triton-2026.png',
            'assets/images/vehicles/pajero-sport-2026.png',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_shared_layout_contains_persistent_theme_system(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/customers')
            ->assertOk()
            ->assertSee('mitsubishi-crm-theme', false)
            ->assertSee('id="themeToggle"', false)
            ->assertSee('--color-surface', false)
            ->assertSee('[data-theme="light"]', false);
    }

    public function test_page_loader_uses_the_dhs_motors_brand_asset(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="pageLoader"', false)
            ->assertSee('/assets/images/brand/honda-emblem-transparent.png', false)
            ->assertSee('Drive Your Dreams')
            ->assertSee('page-loader__ecg', false)
            ->assertSee('dhs-ecg-travel', false)
            ->assertSee('mitsubishi-crm-transitioning', false);
    }

    public function test_all_customer_experience_pages_render_successfully(): void
    {
        foreach (['/customer/login', '/customer/register', '/customer/forgot-password'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->assertPagesRender(UserRole::Customer, [
            '/customer', '/customer/dashboard', '/customer/vehicles',
            '/customer/vehicles/outlander', '/customer/conversations',
            '/customer/conversations/uttara', '/customer/test-drives',
            '/customer/test-drives/book', '/customer/test-drives/confirmed',
            '/customer/bookings', '/customer/bookings/BK-00124', '/customer/garage',
            '/customer/payments', '/customer/profile', '/customer/settings',
        ]);
    }

    public function test_public_and_shared_pages_render_or_redirect_as_expected(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /** @param list<string> $uris */
    private function assertPagesRender(UserRole $role, array $uris): void
    {
        $assignedDealer = in_array($role, [UserRole::Dealer, UserRole::Salesman], true)
            ? Dealer::factory()->create()
            : null;
        $user = User::factory()->create([
            'role' => $role,
            'dealer_id' => $assignedDealer?->id,
        ]);

        if ($role === UserRole::Salesman) {
            $customer = Customer::factory()->create(['salesman_id' => $user->id]);
            $uris = array_map(
                fn (string $uri) => str_replace('{customer}', (string) $customer->id, $uri),
                $uris,
            );
        } elseif ($role === UserRole::Dealer) {
            $salesman = User::factory()->create([
                'role' => UserRole::Salesman,
                'dealer_id' => $assignedDealer->id,
            ]);
            $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);
            $uris = array_map(
                fn (string $uri) => str_replace('{customer}', (string) $customer->id, $uri),
                $uris,
            );
        }

        foreach ($uris as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }
}
