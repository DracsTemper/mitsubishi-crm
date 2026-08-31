<?php

namespace Tests\Feature;

use App\Enums\DealerStatus;
use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dealer_factory_persists_the_foundation_fields(): void
    {
        $dealer = Dealer::factory()->create();

        $this->assertDatabaseHas('dealers', [
            'id' => $dealer->id,
            'name' => $dealer->name,
            'code' => $dealer->code,
            'phone' => $dealer->phone,
            'email' => $dealer->email,
            'address' => $dealer->address,
            'city' => $dealer->city,
            'status' => DealerStatus::Active->value,
        ]);
        $this->assertSame(DealerStatus::Active, $dealer->status);
    }

    public function test_dealer_code_must_be_unique(): void
    {
        Dealer::factory()->create(['code' => 'TEST-UNIQUE']);

        $this->expectException(QueryException::class);

        Dealer::factory()->create(['code' => 'TEST-UNIQUE']);
    }

    public function test_dealer_can_be_inactive(): void
    {
        $dealer = Dealer::factory()->create(['status' => DealerStatus::Inactive]);

        $this->assertSame(DealerStatus::Inactive, $dealer->fresh()->status);
    }

    public function test_dealer_user_relationship_works_in_both_directions(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'role' => UserRole::Dealer,
        ]);

        $this->assertTrue($user->dealer->is($dealer));
        $this->assertTrue($dealer->users->contains($user));
    }

    public function test_deleting_a_dealer_preserves_the_user_and_nulls_the_relationship(): void
    {
        $dealer = Dealer::factory()->create();
        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'role' => UserRole::Dealer,
        ]);

        $dealer->delete();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'dealer_id' => null]);
    }
}
