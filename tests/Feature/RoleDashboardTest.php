<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleProvider')]
    public function test_login_redirects_each_role_to_its_dashboard(UserRole $role): void
    {
        $user = User::factory()->create([
            'password' => 'valid-password',
            'role' => $role,
        ]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'valid-password',
        ])->assertRedirect(route($role->dashboardRouteName()));

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('roleProvider')]
    public function test_guests_are_redirected_from_every_dashboard(UserRole $role): void
    {
        $this->get(route($role->dashboardRouteName()))
            ->assertRedirect(route('login'));
    }

    #[DataProvider('roleProvider')]
    public function test_each_role_can_render_its_dashboard(UserRole $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'dealer_id' => $role === UserRole::Salesman ? Dealer::factory()->create()->id : null,
        ]);

        $this->actingAs($user)
            ->get(route($role->dashboardRouteName()))
            ->assertOk();
    }

    #[DataProvider('wrongRoleProvider')]
    public function test_each_role_is_forbidden_from_other_dashboards(
        UserRole $userRole,
        UserRole $dashboardRole,
    ): void {
        $user = User::factory()->create(['role' => $userRole]);

        $this->actingAs($user)
            ->get(route($dashboardRole->dashboardRouteName()))
            ->assertForbidden();
    }

    #[DataProvider('roleProvider')]
    public function test_shared_dashboard_redirects_to_the_authenticated_users_dashboard(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route($role->dashboardRouteName()));
    }

    public function test_prototype_role_shortcuts_open_login_context_without_bypassing_authentication(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('login', ['context' => 'dealer']), false);
        $response->assertSee(route('login', ['context' => 'salesman']), false);
        $response->assertDontSee('href="'.route('dealer.dashboard').'"', false);
        $response->assertDontSee('href="'.route('salesman.dashboard').'"', false);
        $this->assertGuest();
    }

    public function test_login_context_is_presentation_only(): void
    {
        $response = $this->get(route('login', ['context' => 'dealer']));

        $response->assertOk()->assertSee('Sign in to the Dealer workspace');
        $response->assertDontSee('name="role"', false);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function roleProvider(): array
    {
        return [
            'admin' => [UserRole::Admin],
            'dealer' => [UserRole::Dealer],
            'salesman' => [UserRole::Salesman],
            'customer' => [UserRole::Customer],
        ];
    }

    /**
     * @return array<string, array{UserRole, UserRole}>
     */
    public static function wrongRoleProvider(): array
    {
        $combinations = [];

        foreach (UserRole::cases() as $userRole) {
            foreach (UserRole::cases() as $dashboardRole) {
                if ($userRole !== $dashboardRole) {
                    $combinations[$userRole->value.' to '.$dashboardRole->value] = [$userRole, $dashboardRole];
                }
            }
        }

        return $combinations;
    }
}
