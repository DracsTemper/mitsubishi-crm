<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\DealerVehicleAllocation;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BusinessCalendar;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDataSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Database\Seeders\DevelopmentTestDriveSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DevelopmentDemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fail before any schema command if the test runner is misconfigured.
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Demo tests require testing SQLite :memory:.');
        }
        Carbon::setTestNow('2026-09-13 09:00:00');
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_database_has_complete_demo_and_payment_chain(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        foreach (['dealers' => 5, 'users' => 22, 'vehicles' => 8, 'dealer_vehicle_allocations' => 16,
            'customers' => 4, 'test_drives' => 4, 'bookings' => 1] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertDatabaseHas('dealers', ['code' => 'MUT-001']);
        $this->assertDatabaseHas('dealers', ['code' => 'MDM-002']);
        $this->assertGreaterThan(0, TestDriveSlot::count());
        $booking = Booking::sole();
        $this->assertSame('booking', $booking->testDrive->outcome->value);
        $this->assertSame('500000.00', $booking->total_paid);
        $this->assertSame('4500000.00', $booking->due_amount);
    }

    public function test_repeat_runs_preserve_all_rows_passwords_and_original_dates(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        $admin = User::where('email', 'admin@mitsubishi.test')->sole();
        $admin->update(['password' => Hash::make('ChangedByPresenter!')]);
        $before = $this->snapshot();
        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue(Hash::check('ChangedByPresenter!', $admin->fresh()->password));
    }

    public function test_conflicting_account_rolls_back_all_new_fixture_rows(): void
    {
        User::factory()->create(['email' => 'admin@mitsubishi.test', 'role' => UserRole::Customer]);
        $before = $this->snapshot();
        $this->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_conflicting_required_dealer_is_not_overwritten(): void
    {
        Dealer::factory()->create(['code' => 'MUT-001', 'name' => 'Unrelated existing business']);
        $before = $this->snapshot();
        $this->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_changed_allocation_is_not_reset(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        DealerVehicleAllocation::firstOrFail()->update(['quantity' => 99]);
        $before = $this->snapshot();
        $this->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_duplicate_customer_identity_is_rejected_without_changes(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        Customer::where('email', DevelopmentTestDriveSeeder::CUSTOMER_EMAIL)->sole()->replicate()->save();
        $before = $this->snapshot();
        $this->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_unrelated_records_and_slots_are_preserved(): void
    {
        $vehicle = Vehicle::factory()->create(['name' => 'Outlander', 'variant' => 'Unrelated variant']);
        $allocation = $vehicle->demoAllocations()->sole();
        $slot = TestDriveSlot::create([
            'vehicle_id' => $vehicle->id, 'dealer_vehicle_allocation_id' => $allocation->id,
            'slot_date' => '2027-01-04', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
        ]);
        $originalVehicle = $vehicle->fresh()->getRawOriginal();
        $originalSlot = $slot->fresh()->getRawOriginal();
        $this->seed(DevelopmentDemoSeeder::class);
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame($originalVehicle, $vehicle->fresh()->getRawOriginal());
        $this->assertSame($originalSlot, $slot->fresh()->getRawOriginal());
        $this->assertSame(1, $allocation->testDriveSlots()->count());
    }

    public function test_extra_slot_on_demo_allocation_is_preserved_on_repeat(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        $allocation = DealerVehicleAllocation::firstOrFail();
        TestDriveSlot::create([
            'vehicle_id' => $allocation->vehicle_id, 'dealer_vehicle_allocation_id' => $allocation->id,
            'slot_date' => '2027-02-01', 'start_time' => '11:00:00', 'end_time' => '11:30:00',
        ]);
        $before = $this->snapshot();
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_all_workflow_relationships_match_the_assigned_dealer(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        foreach (TestDrive::with(['customer.salesman', 'slot.demoAllocation'])->get() as $drive) {
            $this->assertSame($drive->salesman_id, $drive->customer->salesman_id);
            $this->assertSame(UserRole::Salesman, $drive->customer->salesman->role);
            $this->assertNotNull($drive->customer->salesman->dealer_id);
            $this->assertSame($drive->customer->salesman->dealer_id, $drive->slot->demoAllocation->dealer_id);
            $this->assertSame($drive->vehicle_id, $drive->slot->vehicle_id);
            $this->assertSame($drive->scheduled_date->toDateString(), $drive->slot->slot_date->toDateString());
        }
        $booking = Booking::sole();
        foreach (['customer_id', 'salesman_id', 'vehicle_id'] as $field) {
            $this->assertSame($booking->$field, $booking->testDrive->$field);
        }
    }

    public function test_initial_dates_respect_past_future_fridays_and_holidays(): void
    {
        config(['business-calendar.holidays' => [
            '2026-09-12' => 'Demo closure', '2026-09-15' => 'Demo closure',
        ]]);
        $this->seed(DevelopmentDemoSeeder::class);
        $calendar = app(BusinessCalendar::class);
        foreach (TestDriveSlot::all() as $slot) {
            $this->assertTrue($calendar->isOpen($slot->slot_date));
            $this->assertFalse($slot->slot_date->isFriday());
        }
        foreach (TestDrive::all() as $drive) {
            if ($drive->status->value === 'completed') {
                $this->assertTrue($drive->scheduled_date->lt(today()));
            } else {
                $this->assertTrue($drive->scheduled_date->gt(today()));
            }
        }
        $followUp = TestDrive::where('outcome', 'follow_up')->sole();
        $this->assertTrue($followUp->follow_up_date->gt(today()));
        $this->assertTrue($calendar->isOpen($followUp->follow_up_date));
    }

    public function test_weekend_does_not_assign_two_drives_to_the_same_salesman_window(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        $this->assertSame(4, TestDrive::get()->unique(fn ($drive) => $drive->salesman_id.'|'.$drive->scheduled_date->toDateString().'|'.$drive->scheduled_time)->count());
    }

    public function test_new_holiday_on_existing_appointment_stops_without_moving_it(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        $date = TestDrive::firstOrFail()->scheduled_date->toDateString();
        config(['business-calendar.holidays' => [$date => 'New closure']]);
        $before = $this->snapshot();
        $this->assertConflict();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_presentation_credentials_authenticate_through_the_real_login_route(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);
        foreach (['admin@mitsubishi.test', 'dealer@mitsubishi.test', 'salesman@mitsubishi.test',
            'customer@mitsubishi.test', DevelopmentTestDriveSeeder::SALESMAN_EMAIL,
            'dealer.gulshan.01@example.test'] as $email) {
            $user = User::where('email', $email)->sole();
            $this->post(route('login.submit'), ['email' => $email, 'password' => DevelopmentDataSeeder::PASSWORD])
                ->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect();
            $this->assertGuest();
        }
    }

    public function test_orchestrator_rejects_non_demo_database_before_inserts(): void
    {
        config(['database.connections.sqlite.database' => '/not-the-demo.sqlite']);
        DB::purge('sqlite');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Demo seeding requires');
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_default_seeder_does_nothing_outside_local_environment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_normal_local_database_seeder_only_creates_original_login_accounts(): void
    {
        app()->instance('env', 'local');
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('dealers', 0);
        $this->assertDatabaseCount('test_drives', 0);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertTrue(Hash::check('password', User::where('email', 'admin@mitsubishi.test')->sole()->password));
    }

    private function assertConflict(): void
    {
        try {
            $this->seed(DevelopmentDemoSeeder::class);
            $this->fail('Expected a fixture conflict.');
        } catch (LogicException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['dealers', 'users', 'vehicles', 'dealer_vehicle_allocations', 'customers',
            'test_drive_slots', 'test_drives', 'bookings'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }
}
