<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_invalid_credentials_are_rejected_without_authenticating(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_uses_the_same_login_flow(UserRole $role): void
    {
        $user = User::factory()->create([
            'password' => 'valid-password',
            'role' => $role,
        ]);

        $response = $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'valid-password',
            'remember' => true,
        ]);

        $response->assertRedirect(route($role->dashboardRouteName()));
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
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
}
