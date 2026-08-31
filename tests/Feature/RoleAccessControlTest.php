<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleRouteProvider')]
    public function test_guests_are_redirected_from_every_role_route(UserRole $routeRole): void
    {
        $this->get(route($routeRole->value.'.access-test'))
            ->assertRedirect(route('login'));
    }

    #[DataProvider('roleRouteProvider')]
    public function test_each_role_can_access_its_own_route(UserRole $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'dealer_id' => $role === UserRole::Salesman ? Dealer::factory()->create()->id : null,
        ]);

        $this->actingAs($user)
            ->get(route($role->value.'.access-test'))
            ->assertOk()
            ->assertSee(ucfirst($role->value).' access granted');
    }

    #[DataProvider('wrongRoleProvider')]
    public function test_each_role_is_forbidden_from_other_role_routes(
        UserRole $userRole,
        UserRole $routeRole,
    ): void {
        $user = User::factory()->create(['role' => $userRole]);

        $this->actingAs($user)
            ->get(route($routeRole->value.'.access-test'))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function roleRouteProvider(): array
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
            foreach (UserRole::cases() as $routeRole) {
                if ($userRole !== $routeRole) {
                    $combinations[$userRole->value.' to '.$routeRole->value] = [$userRole, $routeRole];
                }
            }
        }

        return $combinations;
    }
}
