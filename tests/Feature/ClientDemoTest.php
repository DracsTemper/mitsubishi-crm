<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\TestDrive;
use App\Models\TestDriveSlot;
use App\Models\User;
use App\Services\BusinessCalendar;
use App\Models\DealerVehicleAllocation;
use App\Models\Vehicle;
use Database\Seeders\ClientDemoSeeder;
use Database\Seeders\DevelopmentDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class ClientDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Client demo tests require isolated testing SQLite :memory:.');
        }
        Carbon::setTestNow('2026-09-14 09:00:00');
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_complete_fictional_workflow_has_valid_ownership_dates_and_payments(): void
    {
        config(['business-calendar.holidays' => ['2026-09-16' => 'Fictional test closure']]);
        $this->seed(ClientDemoSeeder::class);
        foreach (['dealers' => 5, 'users' => 27, 'vehicles' => 8, 'dealer_vehicle_allocations' => 16,
            'customers' => 100, 'test_drives' => 100, 'bookings' => 37] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame(['cancelled' => 12, 'confirmed' => 12, 'pending' => 13], Booking::selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->all());
        foreach (TestDrive::with(['slot.demoAllocation', 'customer.salesman'])->get() as $drive) {
            $this->assertSame($drive->salesman_id, $drive->customer->salesman_id);
            $this->assertSame($drive->customer->salesman->dealer_id, $drive->slot->demoAllocation->dealer_id);
            $this->assertSame($drive->vehicle_id, $drive->slot->vehicle_id);
            $this->assertSame($drive->scheduled_date->toDateString(), $drive->slot->slot_date->toDateString());
            $this->assertSame($drive->scheduled_time, $drive->slot->start_time);
            $this->assertTrue($drive->status->value === 'completed' ? $drive->scheduled_date->lt(today()) : $drive->scheduled_date->gte(today()));
        }
        foreach (TestDriveSlot::all() as $slot) {
            $this->assertTrue(app(BusinessCalendar::class)->isOpen($slot->slot_date));
            $this->assertSame(app(BusinessCalendar::class)->endTimeFor($slot->start_time), substr($slot->end_time, 0, 5));
        }
        foreach (Booking::with(['testDrive', 'vehicle'])->get() as $booking) {
            foreach (['customer_id', 'salesman_id', 'vehicle_id'] as $field) {
                $this->assertSame($booking->$field, $booking->testDrive->$field);
            }
            $this->assertSame('completed', $booking->testDrive->status->value);
            $this->assertSame('booking', $booking->testDrive->outcome->value);
            $this->assertEquals((float) $booking->vehicle->price, (float) $booking->total_paid + (float) $booking->due_amount);
            $this->assertTrue($booking->expected_delivery_date->gte($booking->booking_date));
        }
        $this->assertSame(0, DB::table('test_drives')->selectRaw('salesman_id, scheduled_date, scheduled_time, COUNT(*) AS total')
            ->groupBy('salesman_id', 'scheduled_date', 'scheduled_time')->havingRaw('COUNT(*) > 1')->get()->count());
    }

    public function test_repeated_setup_preserves_records_dates_and_passwords(): void
    {
        $this->seed(ClientDemoSeeder::class);
        User::where('email', 'salesman@mitsubishi.test')->firstOrFail()->update(['password' => Hash::make('PresenterChangedThis!')]);
        $before = $this->snapshot();
        Carbon::setTestNow('2026-11-02');
        $this->seed(ClientDemoSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_late_conflict_rolls_back_all_new_fixtures(): void
    {
        User::factory()->create(['email' => 'customer.tania@example.test', 'name' => 'Unrelated account']);
        $before = $this->snapshot();
        try {
            $this->seed(ClientDemoSeeder::class);
            $this->fail('Expected conflict');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Conflicting demo record', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_extra_allocations_do_not_change_demo_vehicle_selection(): void
    {
        $this->seed(ClientDemoSeeder::class);
        $owner = User::where('email', 'salesman@mitsubishi.test')->sole();
        Vehicle::factory()->create(['dealer_id' => $owner->dealer_id, 'name' => 'Unrelated showroom vehicle']);
        $historical = Vehicle::where('name', 'Lancer Evolution')->sole();
        DealerVehicleAllocation::create(['dealer_id' => $owner->dealer_id, 'vehicle_id' => $historical->id, 'quantity' => 1, 'status' => 'active']);
        $before = $this->snapshot();
        $this->seed(ClientDemoSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_accounts_login_and_staff_pages_render_with_scoped_data(): void
    {
        $this->seed(ClientDemoSeeder::class);
        foreach (User::where('email', 'not like', '%unassigned%')->get() as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => DevelopmentDataSeeder::PASSWORD])->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->get(route($user->role->dashboardRouteName()))->assertOk();
            $this->post('/logout')->assertRedirect();
        }
        $admin = User::where('email', 'admin@mitsubishi.test')->sole();
        $this->actingAs($admin);
        foreach (['/customers', '/dealers', '/inventory', '/bookings', '/calendar', '/reports'] as $url) {
            $this->get($url)->assertOk();
        }
        $dealer = User::where('email', 'dealer@mitsubishi.test')->sole();
        $this->actingAs($dealer);
        foreach (['dashboard', 'customers', 'salesmen', 'inventory', 'bookings', 'calendar', 'reports'] as $path) {
            $this->get('/dealer/'.$path)->assertOk();
        }
        $salesman = User::where('email', 'salesman@mitsubishi.test')->sole();
        $this->actingAs($salesman);
        foreach (['dashboard', 'customers', 'test-drives', 'calendar', 'bookings', 'follow-ups'] as $path) {
            $this->get('/salesman/'.$path)->assertOk();
        }
        $this->assertSame(100, Customer::count());
        $this->assertSame(9, $salesman->customers()->count());
        $foreign = Customer::whereHas('salesman', fn ($query) => $query->where('dealer_id', '!=', $dealer->dealer_id))->firstOrFail();
        $this->get('/salesman/customers/'.$foreign->id)->assertNotFound();
        $this->actingAs($dealer)->get('/dealer/customers/'.$foreign->id)->assertNotFound();
        $this->actingAs(User::where('email', 'dealer.unassigned@example.test')->sole())->get('/dealer/customers')->assertForbidden();
        $this->actingAs(User::where('email', 'salesman.unassigned@example.test')->sole())->get('/salesman/dashboard')->assertForbidden();
        $this->actingAs(User::where('email', 'customer@mitsubishi.test')->sole())->get('/admin/dashboard')->assertForbidden();
    }

    public function test_prepare_command_uses_ordinary_migrations_and_second_run_creates_nothing(): void
    {
        $this->artisan('demo:prepare')->assertExitCode(0);
        $before = $this->snapshot();
        $this->artisan('demo:prepare')->assertExitCode(0);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_presenter_can_book_ready_drive_and_schedule_free_slot(): void
    {
        $this->seed(ClientDemoSeeder::class);
        $user = User::where('email', 'salesman@mitsubishi.test')->sole();
        $this->actingAs($user);
        $drive = TestDrive::where('salesman_id', $user->id)->where('status', 'completed')->whereNull('outcome')->firstOrFail();
        $url = route('salesman.customers.test-drives.book.store', [$drive->customer_id, $drive->id]);
        $this->post($url, ['booking_date' => today()->toDateString(), 'booking_amount' => 100000,
            'expected_delivery_date' => today()->addDays(30)->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('bookings', 38);
        $this->post($url, ['booking_date' => today()->toDateString(), 'booking_amount' => 100000])->assertNotFound();
        $slot = TestDriveSlot::whereHas('demoAllocation', fn ($query) => $query->where('dealer_id', $user->dealer_id))
            ->whereDate('slot_date', '>', today())->whereDoesntHave('testDrive')->firstOrFail();
        $this->post(route('salesman.customers.test-drives.store', $drive->customer_id), ['slot_id' => $slot->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('test_drives', ['slot_id' => $slot->id, 'salesman_id' => $user->id]);
        $this->get('/salesman/dashboard')->assertOk();
    }

    public function test_remote_mysql_and_production_targets_are_rejected_before_connection(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => 'example.invalid',
            'database.connections.mysql.database' => 'mitsubishi_crm_demo']);
        app()->instance('env', 'local');
        try {
            \Database\Seeders\DevelopmentDemoSeeder::assertTarget();
            $this->fail('Remote host must not be accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Demo seeding requires', $exception->getMessage());
        }
        config(['database.connections.mysql.host' => '127.0.0.1']);
        DB::purge('mysql');
        app()->instance('env', 'production');
        $this->expectException(LogicException::class);
        \Database\Seeders\DevelopmentDemoSeeder::assertTarget();
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['dealers', 'users', 'vehicles', 'dealer_vehicle_allocations', 'customers', 'test_drive_slots', 'test_drives', 'bookings'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $result;
    }
}
