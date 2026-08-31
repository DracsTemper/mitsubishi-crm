<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SalesmanDealerScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', 'role:salesman', 'salesman.dealer'])
            ->get('/_tests/salesman/dealers/{dealer}', function (Request $request, string $dealer) {
                return response()->json([
                    'route_dealer_id' => (int) $dealer,
                    'authenticated_dealer_id' => $request->attributes
                        ->get('salesmanDealer')?->id,
                ]);
            });
    }

    public function test_assigned_salesman_can_access_their_workspace(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();

        $this->actingAs($salesman)
            ->get(route('salesman.dashboard'))
            ->assertOk();

        $this->actingAs($salesman)
            ->get("/_tests/salesman/dealers/{$dealer->id}")
            ->assertOk()
            ->assertJson([
                'route_dealer_id' => $dealer->id,
                'authenticated_dealer_id' => $dealer->id,
            ]);
    }

    public function test_route_parameter_cannot_select_another_dealer(): void
    {
        [, $salesman] = $this->assignedSalesman();
        $otherDealer = Dealer::factory()->create();

        $this->actingAs($salesman)
            ->get("/_tests/salesman/dealers/{$otherDealer->id}")
            ->assertForbidden();
    }

    public function test_query_parameter_cannot_override_authenticated_dealer_scope(): void
    {
        [$dealer, $salesman] = $this->assignedSalesman();
        $otherDealer = Dealer::factory()->create();

        $this->actingAs($salesman)
            ->get(route('salesman.dashboard', ['dealer_id' => $dealer->id]))
            ->assertOk();

        $this->actingAs($salesman)
            ->get(route('salesman.dashboard', ['dealer_id' => $otherDealer->id]))
            ->assertForbidden();
    }

    public function test_unassigned_salesman_is_denied_dealer_scoped_workspace(): void
    {
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => null,
        ]);

        $this->actingAs($salesman)
            ->get(route('salesman.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_still_redirected_to_login(): void
    {
        $this->get(route('salesman.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_other_role_workspaces_are_unaffected(): void
    {
        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route($role->dashboardRouteName()))
                ->assertOk();
        }
    }

    /** @return array{Dealer, User} */
    private function assignedSalesman(): array
    {
        $dealer = Dealer::factory()->create();

        return [
            $dealer,
            User::factory()->create([
                'role' => UserRole::Salesman,
                'dealer_id' => $dealer->id,
            ]),
        ];
    }
}
