<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class CustomerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_and_required_fields_are_persisted(): void
    {
        $salesman = $this->salesman();

        $customer = Customer::query()->create([
            'salesman_id' => $salesman->id,
            'name' => 'Fixture Customer Alpha',
            'phone' => '000-1400-0001',
            'email' => 'customer.alpha@example.test',
            'address' => '14 Foundation Test Road',
            'city' => 'Test City',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'salesman_id' => $salesman->id,
            'name' => 'Fixture Customer Alpha',
            'phone' => '000-1400-0001',
            'email' => 'customer.alpha@example.test',
            'address' => '14 Foundation Test Road',
            'city' => 'Test City',
            'status' => 'new',
        ]);
    }

    public function test_customer_factory_creates_a_valid_salesman_and_dealer_chain(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->salesman);
        $this->assertSame(UserRole::Salesman, $customer->salesman->role);
        $this->assertNotNull($customer->salesman->dealer);
    }

    public function test_customer_and_salesman_relationships_work_in_both_directions(): void
    {
        $salesman = $this->salesman();
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->assertTrue($customer->salesman->is($salesman));
        $this->assertTrue($salesman->customers->contains($customer));
    }

    public function test_salesman_foreign_key_rejects_a_missing_user(): void
    {
        $this->expectException(QueryException::class);

        DB::table('customers')->insert([
            'salesman_id' => 999999,
            'name' => 'Invalid Owner Fixture',
            'phone' => '000-1400-0099',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_non_salesman_users_are_rejected_as_customer_owners(): void
    {
        foreach ([UserRole::Admin, UserRole::Dealer, UserRole::Customer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $customer = Customer::factory()->make(['salesman_id' => $user->id]);

            try {
                $customer->save();
                $this->fail("The {$role->value} role was accepted as a Customer owner.");
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'A Customer owner must be an existing Salesman user.',
                    $exception->getMessage(),
                );
            }
        }

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_dealer_is_derived_through_the_salesman(): void
    {
        $dealer = Dealer::factory()->create();
        $salesman = $this->salesman($dealer);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->assertTrue($customer->salesman->dealer->is($dealer));
        $this->assertArrayNotHasKey('dealer_id', $customer->getAttributes());
    }

    public function test_customer_owned_by_unassigned_salesman_has_no_derived_dealer(): void
    {
        $salesman = User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => null,
        ]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        $this->assertNull($customer->salesman->dealer);
    }

    public function test_relationships_keep_customers_isolated_by_salesman_and_dealer(): void
    {
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $salesmanA = $this->salesman($dealerA);
        $salesmanB = $this->salesman($dealerB);
        $customerA = Customer::factory()->create(['salesman_id' => $salesmanA->id]);
        $customerB = Customer::factory()->create(['salesman_id' => $salesmanB->id]);

        $this->assertTrue($salesmanA->customers->contains($customerA));
        $this->assertFalse($salesmanA->customers->contains($customerB));
        $this->assertTrue($salesmanB->customers->contains($customerB));
        $this->assertFalse($salesmanB->customers->contains($customerA));
        $this->assertTrue($customerA->salesman->dealer->is($dealerA));
        $this->assertTrue($customerB->salesman->dealer->is($dealerB));
    }

    private function salesman(?Dealer $dealer = null): User
    {
        $dealer ??= Dealer::factory()->create();

        return User::factory()->create([
            'role' => UserRole::Salesman,
            'dealer_id' => $dealer->id,
        ]);
    }
}
