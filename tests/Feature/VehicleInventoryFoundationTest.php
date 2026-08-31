<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DevelopmentVehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class VehicleInventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_can_be_created_with_dealer_status_and_existing_image_path(): void
    {
        $dealer = Dealer::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Outlander',
            'variant' => '2.4 AWD',
            'image' => 'assets/images/vehicles/outlander-2026.png',
            'status' => 'available',
        ]);

        $this->assertModelExists($vehicle);
        $this->assertTrue($vehicle->dealer->is($dealer));
        $this->assertTrue($dealer->vehicles()->whereKey($vehicle)->exists());
        $this->assertSame('assets/images/vehicles/outlander-2026.png', $vehicle->image);
        $this->assertFileExists(public_path($vehicle->image));
    }

    public function test_every_supported_status_is_accepted_and_unsupported_status_is_rejected(): void
    {
        $dealer = Dealer::factory()->create();
        foreach (Vehicle::STATUSES as $status) {
            $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'status' => $status]);
            $this->assertSame($status, $vehicle->status);
        }

        $this->expectException(InvalidArgumentException::class);
        Vehicle::factory()->create(['dealer_id' => $dealer->id, 'status' => 'unsupported']);
    }

    public function test_vehicle_schema_excludes_physical_unit_and_salesman_ownership_fields(): void
    {
        foreach (['chassis_number', 'vin', 'engine_number', 'salesman_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('vehicles', $column));
        }

        $vehicle = Vehicle::factory()->create();
        $this->assertModelExists($vehicle);
    }

    public function test_vehicle_creation_does_not_change_existing_crm_records(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $salesman = User::factory()->create(['role' => UserRole::Salesman, 'dealer_id' => $dealer->id]);
        $customer = Customer::factory()->create(['salesman_id' => $salesman->id]);

        Vehicle::factory()->create(['dealer_id' => $dealer->id]);

        $this->assertModelExists($dealer);
        $this->assertModelExists($manager);
        $this->assertModelExists($salesman);
        $this->assertModelExists($customer);
    }

    public function test_development_seeder_preserves_four_prototype_vehicles_and_is_idempotent(): void
    {
        foreach (['MUT-001', 'TEST-F13-GUL', 'MDM-002', 'TEST-F13-DHN'] as $code) {
            Dealer::factory()->create(['code' => $code]);
        }

        $this->seed(DevelopmentVehicleSeeder::class);
        $this->seed(DevelopmentVehicleSeeder::class);

        $this->assertDatabaseCount('vehicles', 4);
        foreach ([
            ['Outlander', '2.4 AWD', 'Graphite Gray', 'reserved', 'assets/images/vehicles/outlander-2026.png'],
            ['Xforce', '1.5 Premium', 'Pearl White', 'available', 'assets/images/vehicles/xforce-2026.png'],
            ['Triton', '2.4 Double Cab', 'Red Diamond', 'sold', 'assets/images/vehicles/triton-2026.png'],
            ['Pajero Sport', '2.4 Elite', 'Jet Black', 'test_drive', 'assets/images/vehicles/pajero-sport-2026.png'],
        ] as [$name, $variant, $color, $status, $image]) {
            $this->assertDatabaseHas('vehicles', compact('name', 'variant', 'color', 'status', 'image'));
            $this->assertFileExists(public_path($image));
        }
    }

    public function test_admin_sees_vehicles_globally_and_other_roles_cannot_access_admin_inventory(): void
    {
        $first = Vehicle::factory()->create(['dealer_id' => Dealer::factory(), 'name' => 'Admin Global Outlander']);
        $second = Vehicle::factory()->create(['dealer_id' => Dealer::factory(), 'name' => 'Admin Global Triton']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('vehicles.index'))
            ->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->get(route('inventory'))->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->post(route('logout'));
        $this->get(route('vehicles.index'))->assertRedirect(route('login'));
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('vehicles.index'))->assertForbidden();
        }
    }

    public function test_manager_inventory_is_database_scoped_to_authenticated_dealer(): void
    {
        $dealer = Dealer::factory()->create();
        $otherDealer = Dealer::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $owned = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Manager Owned Vehicle']);
        $other = Vehicle::factory()->create(['dealer_id' => $otherDealer->id, 'name' => 'Other Dealer Vehicle']);

        $this->actingAs($manager)->get(route('dealer.inventory', ['role' => UserRole::Admin->value]))
            ->assertOk()->assertSee($owned->name)->assertDontSee($other->name);
        $this->get(route('dealer.inventory', ['dealer_id' => $otherDealer->id]))->assertForbidden();
        $this->assertSame($dealer->id, $owned->fresh()->dealer_id);
    }

    public function test_unassigned_manager_and_non_manager_roles_cannot_access_manager_inventory(): void
    {
        $this->get(route('dealer.inventory'))->assertRedirect(route('login'));
        $unassigned = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => null]);
        $this->actingAs($unassigned)->get(route('dealer.inventory'))->assertForbidden();
        foreach ([UserRole::Admin, UserRole::Salesman, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('dealer.inventory'))->assertForbidden();
        }
    }
}
