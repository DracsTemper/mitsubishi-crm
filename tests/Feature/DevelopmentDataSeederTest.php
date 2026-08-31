<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\DevelopmentDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_dealers_and_role_assignments(): void
    {
        $this->seed(DevelopmentDataSeeder::class);

        $this->assertDatabaseCount('dealers', 3);
        $this->assertSame(3, Dealer::query()->distinct()->count('code'));

        foreach (DevelopmentDataSeeder::DEALERS as $key => $fixture) {
            $dealer = Dealer::query()->where('code', $fixture['code'])->firstOrFail();
            $dealerUsers = User::query()
                ->where('dealer_id', $dealer->id)
                ->where('role', UserRole::Dealer->value)
                ->orderBy('email')
                ->get();
            $salesmen = User::query()
                ->where('dealer_id', $dealer->id)
                ->where('role', UserRole::Salesman->value)
                ->orderBy('email')
                ->get();

            $this->assertCount(2, $dealerUsers);
            $this->assertCount(3, $salesmen);

            foreach ($dealerUsers as $number => $user) {
                $this->assertSame(UserRole::Dealer, $user->role);
                $this->assertSame($dealer->id, $user->dealer_id);
                $this->assertSame(
                    sprintf('dealer.%s.%02d@example.test', $key, $number + 1),
                    $user->email,
                );
            }

            foreach ($salesmen as $number => $user) {
                $this->assertSame(UserRole::Salesman, $user->role);
                $this->assertSame($dealer->id, $user->dealer_id);
                $this->assertSame(
                    sprintf('salesman.%s.%02d@example.test', $key, $number + 1),
                    $user->email,
                );
            }
        }

        $this->assertDatabaseHas('users', [
            'email' => 'dealer.unassigned@example.test',
            'role' => UserRole::Dealer->value,
            'dealer_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'salesman.unassigned@example.test',
            'role' => UserRole::Salesman->value,
            'dealer_id' => null,
        ]);
    }

    public function test_fixture_passwords_authenticate_and_are_hashed(): void
    {
        $this->seed(DevelopmentDataSeeder::class);

        foreach ($this->fixtureEmails() as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check(DevelopmentDataSeeder::PASSWORD, $user->password));
            $this->assertNotSame(DevelopmentDataSeeder::PASSWORD, $user->password);
        }
    }

    public function test_seeder_is_idempotent_and_preserves_existing_records(): void
    {
        $admin = User::factory()->create([
            'name' => 'Existing Admin Must Stay Untouched',
            'email' => 'existing.admin@example.test',
            'password' => 'existing-admin-password',
            'role' => UserRole::Admin,
        ]);
        $existingUser = User::factory()->create([
            'name' => 'Existing Non Fixture User',
            'email' => 'existing.user@example.test',
            'role' => UserRole::Dealer,
        ]);
        $existingDealer = Dealer::factory()->create(['code' => 'EXISTING-001']);
        $adminSnapshot = $admin->fresh()->getRawOriginal();
        $userSnapshot = $existingUser->fresh()->getRawOriginal();
        $dealerSnapshot = $existingDealer->fresh()->getRawOriginal();

        $this->seed(DevelopmentDataSeeder::class);
        $countsAfterFirstRun = [Dealer::query()->count(), User::query()->count()];
        $this->seed(DevelopmentDataSeeder::class);

        $this->assertSame([4, 19], $countsAfterFirstRun);
        $this->assertSame($countsAfterFirstRun, [Dealer::query()->count(), User::query()->count()]);
        $this->assertSame($adminSnapshot, $admin->fresh()->getRawOriginal());
        $this->assertSame($userSnapshot, $existingUser->fresh()->getRawOriginal());
        $this->assertSame($dealerSnapshot, $existingDealer->fresh()->getRawOriginal());
    }

    /** @return list<string> */
    private function fixtureEmails(): array
    {
        $emails = ['dealer.unassigned@example.test', 'salesman.unassigned@example.test'];

        foreach (array_keys(DevelopmentDataSeeder::DEALERS) as $key) {
            for ($number = 1; $number <= 2; $number++) {
                $emails[] = sprintf('dealer.%s.%02d@example.test', $key, $number);
            }

            for ($number = 1; $number <= 3; $number++) {
                $emails[] = sprintf('salesman.%s.%02d@example.test', $key, $number);
            }
        }

        return $emails;
    }
}
