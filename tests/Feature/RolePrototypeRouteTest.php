<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePrototypeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_every_role_prototype_route(): void
    {
        $this->createRouteFixtures();

        foreach ($this->roleRoutes() as $routes) {
            foreach ($routes as [$name, $parameters]) {
                $this->get(route($name, $parameters))->assertRedirect(route('login'));
            }
        }
    }

    public function test_correct_roles_can_render_every_role_prototype_route(): void
    {
        $this->createRouteFixtures();

        foreach ($this->roleRoutes() as $roleValue => $routes) {
            $role = UserRole::from($roleValue);
            $user = User::factory()->create([
                'role' => $role,
                'dealer_id' => in_array($role, [UserRole::Dealer, UserRole::Salesman], true) ? 1 : null,
            ]);
            $ownedCustomer = $role === UserRole::Salesman
                ? Customer::factory()->create(['salesman_id' => $user->id])
                : null;

            foreach ($routes as [$name, $parameters]) {
                if ($name === 'salesman.customers.show') {
                    $parameters = [$ownedCustomer->id];
                }

                $this->actingAs($user)->get(route($name, $parameters))->assertOk();
            }
        }
    }

    public function test_wrong_roles_are_forbidden_from_every_role_prototype_route(): void
    {
        $this->createRouteFixtures();

        $users = collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [
            $role->value => User::factory()->create(['role' => $role]),
        ]);

        foreach ($this->roleRoutes() as $routeRole => $routes) {
            foreach ($users->except($routeRole) as $user) {
                foreach ($routes as [$name, $parameters]) {
                    $this->actingAs($user)->get(route($name, $parameters))->assertForbidden();
                }
            }
        }
    }

    public function test_route_names_and_method_uri_pairs_are_unique(): void
    {
        $names = [];
        $methodUris = [];

        foreach (app('router')->getRoutes() as $route) {
            if ($route->getName() !== null) {
                $this->assertArrayNotHasKey($route->getName(), $names);
                $names[$route->getName()] = true;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                $this->assertArrayNotHasKey($key, $methodUris);
                $methodUris[$key] = true;
            }
        }
    }

    /** @return array<string, list<array{string, array<int, int|string>}>> */
    private function roleRoutes(): array
    {
        return [
            UserRole::Admin->value => [
                ['admin.dashboard', []], ['admin.access-test', []],
                ['customers.index', []], ['customers.show', [1]],
                ['conversations', []], ['bookings.index', []], ['bookings.show', [124]],
                ['calendar', []], ['inventory', []], ['reports', []],
                ['integrations', []], ['settings', []], ['dealers.index', []],
                ['dealers.show', [1]], ['salesmen.index', []],
                ['salesmen.show', ['arif-hasan']], ['vehicles.index', []],
                ['test-drives.index', []], ['deliveries.index', []],
            ],
            UserRole::Dealer->value => [
                ['dealer.dashboard', []], ['dealer.access-test', []],
                ['dealer.customers.index', []], ['dealer.customers.show', [1]],
                ['dealer.conversations', []], ['dealer.bookings', []],
                ['dealer.calendar', []], ['dealer.inventory', []],
                ['dealer.performance', []], ['dealer.profile', []], ['dealer.team', []],
                ['dealer.team.show', ['arif-hasan']], ['dealer.test-drives', []],
                ['dealer.chassis', []], ['dealer.deliveries', []], ['dealer.reports', []],
            ],
            UserRole::Salesman->value => [
                ['salesman.dashboard', []], ['salesman.access-test', []],
                ['salesman.customers.index', []], ['salesman.customers.show', [1]],
                ['salesman.conversations', []], ['salesman.test-drives', []],
                ['salesman.calendar', []], ['salesman.bookings', []],
                ['salesman.chassis', []], ['salesman.deliveries', []],
            ],
            UserRole::Customer->value => [
                ['customer.home', []], ['customer.dashboard', []],
                ['customer.access-test', []], ['customer.vehicles', []],
                ['customer.vehicles.show', ['outlander']], ['customer.conversations', []],
                ['customer.conversations.show', ['uttara']], ['customer.test-drives', []],
                ['customer.test-drives.book', []], ['customer.test-drives.confirmed', []],
                ['customer.bookings', []], ['customer.bookings.show', ['BK-00124']],
                ['customer.garage', []], ['customer.payments', []],
                ['customer.profile', []], ['customer.settings', []],
            ],
        ];
    }

    private function createRouteFixtures(): void
    {
        $dealer = Dealer::factory()->create(['id' => 1]);
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);

        Customer::factory()->create(['id' => 1, 'salesman_id' => $salesman->id]);
    }
}
