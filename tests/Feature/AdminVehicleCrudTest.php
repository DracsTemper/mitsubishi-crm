<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Dealer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminVehicleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_open_create_store_and_view_vehicle(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        $existing = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'name' => 'Existing CRUD Vehicle']);

        $this->actingAs($admin)->get(route('vehicles.index'))->assertOk()->assertSee($existing->name);
        $this->get(route('vehicles.create'))->assertOk()->assertSee('Create Vehicle')->assertDontSee('name="dealer_id"', false)->assertSee('Demo Allocation');
        $response = $this->post(route('vehicles.store'), $this->payload($dealer, [
            'role' => UserRole::Admin->value,
            'chassis_number' => 'MALICIOUS-CHASSIS',
            'vin' => 'MALICIOUS-VIN',
            'engine_number' => 'MALICIOUS-ENGINE',
            'salesman_id' => User::factory()->create(['role' => UserRole::Salesman])->id,
        ]));
        $vehicle = Vehicle::query()->where('name', 'Function 30 Vehicle')->firstOrFail();

        $response->assertRedirect(route('vehicles.show', $vehicle))->assertSessionHas('success');
        $this->assertSame($dealer->id, $vehicle->dealer_id);
        $this->assertSame('2.4 Premium', $vehicle->variant);
        $this->assertSame(2026, $vehicle->model_year);
        $this->assertSame('Diamond Red', $vehicle->color);
        $this->assertSame('4500000.00', $vehicle->price);
        $this->assertSame('A database-backed test Vehicle.', $vehicle->description);
        $this->assertSame('available', $vehicle->status);
        $this->get(route('vehicles.show', $vehicle))->assertOk()->assertSee($vehicle->name)->assertSee($dealer->name)->assertSee('Outlet Demo Allocation');
        foreach (['chassis_number', 'vin', 'engine_number', 'salesman_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('vehicles', $column));
        }
    }

    public function test_admin_can_upload_replace_and_delete_managed_primary_image(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload($dealer, [
            'image' => $this->fixtureImage('first.png', 'outlander-2026.png'),
        ]));
        $vehicle = Vehicle::query()->where('name', 'Function 30 Vehicle')->firstOrFail();
        $firstImage = $vehicle->image;
        $this->assertNotNull($firstImage);
        $this->assertStringStartsWith('assets/images/vehicles/uploads/', $firstImage);
        $this->assertFileExists(public_path($firstImage));

        $this->put(route('vehicles.update', $vehicle), $this->payload($dealer, [
            'name' => 'Function 30 Updated Vehicle',
            'image' => $this->fixtureImage('replacement.png', 'xforce-2026.png'),
        ]))->assertRedirect(route('vehicles.show', $vehicle));
        $vehicle->refresh();
        $replacement = $vehicle->image;
        $this->assertNotSame($firstImage, $replacement);
        $this->assertFileDoesNotExist(public_path($firstImage));
        $this->assertFileExists(public_path($replacement));

        $this->delete(route('vehicles.destroy', $vehicle))->assertRedirect(route('vehicles.index'));
        $this->assertModelMissing($vehicle);
        $this->assertFileDoesNotExist(public_path($replacement));
    }

    public function test_update_without_image_preserves_image_and_dealer_is_not_part_of_catalog_form(): void
    {
        $admin = $this->admin();
        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'dealer_id' => $dealerA->id,
            'image' => 'assets/images/vehicles/outlander-2026.png',
        ]);

        $this->actingAs($admin)->get(route('vehicles.edit', $vehicle))->assertOk()->assertDontSee('name="dealer_id"', false)->assertSee('Demo Allocation');
        $this->put(route('vehicles.update', $vehicle), $this->payload($dealerB, ['name' => 'Reassigned Vehicle']))
            ->assertRedirect(route('vehicles.show', $vehicle))->assertSessionHas('success');

        $vehicle->refresh();
        $this->assertSame($dealerB->id, $vehicle->dealer_id);
        $this->assertSame('assets/images/vehicles/outlander-2026.png', $vehicle->image);
        $this->assertFileExists(public_path($vehicle->image));
    }

    public function test_deleting_vehicle_does_not_delete_dealer_users_or_shared_fixture_image(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id, 'image' => 'assets/images/vehicles/xforce-2026.png']);

        $this->actingAs($admin)->delete(route('vehicles.destroy', $vehicle))->assertRedirect(route('vehicles.index'));

        $this->assertModelMissing($vehicle);
        $this->assertModelExists($dealer);
        $this->assertModelExists($manager);
        $this->assertFileExists(public_path('assets/images/vehicles/xforce-2026.png'));
    }

    public function test_vehicle_validation_rejects_invalid_dealer_status_price_and_image(): void
    {
        $admin = $this->admin();
        $dealer = Dealer::factory()->create();

        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload($dealer, [
            'dealer_id' => 999999,
            'status' => 'unsupported',
            'price' => -1,
            'image' => UploadedFile::fake()->create('not-image.txt', 10, 'text/plain'),
        ]))->assertSessionHasErrors(['dealer_id', 'status', 'price', 'image']);
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_guests_and_non_admin_roles_cannot_access_or_mutate_vehicle_crud(): void
    {
        $dealer = Dealer::factory()->create();
        $vehicle = Vehicle::factory()->create(['dealer_id' => $dealer->id]);
        $routes = [
            fn () => $this->get(route('vehicles.index')),
            fn () => $this->get(route('vehicles.create')),
            fn () => $this->post(route('vehicles.store'), $this->payload($dealer)),
            fn () => $this->get(route('vehicles.show', $vehicle)),
            fn () => $this->get(route('vehicles.edit', $vehicle)),
            fn () => $this->put(route('vehicles.update', $vehicle), $this->payload($dealer)),
            fn () => $this->delete(route('vehicles.destroy', $vehicle)),
        ];

        foreach ($routes as $request) {
            $request()->assertRedirect(route('login'));
        }
        foreach ([UserRole::Dealer, UserRole::Salesman, UserRole::Customer] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            foreach ($routes as $request) {
                $this->actingAs($actor);
                $request()->assertForbidden();
            }
        }
        $this->assertModelExists($vehicle);
    }

    public function test_browser_role_cannot_escalate_vehicle_crud_access(): void
    {
        $dealer = Dealer::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Dealer, 'dealer_id' => $dealer->id]);

        $this->actingAs($manager)->post(route('vehicles.store'), $this->payload($dealer, ['role' => UserRole::Admin->value]))
            ->assertForbidden();
        $this->assertDatabaseCount('vehicles', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function fixtureImage(string $name, string $fixture): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            file_get_contents(public_path('assets/images/vehicles/'.$fixture)),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Dealer $dealer, array $overrides = []): array
    {
        return array_merge([
            'dealer_id' => $dealer->id,
            'name' => 'Function 30 Vehicle',
            'variant' => '2.4 Premium',
            'model_year' => 2026,
            'color' => 'Diamond Red',
            'price' => 4500000,
            'description' => 'A database-backed test Vehicle.',
            'status' => 'available',
        ], $overrides);
    }
}
