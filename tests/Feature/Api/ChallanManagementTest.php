<?php

namespace Tests\Feature\Api;

use App\Challan;
use App\Driver;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_and_list_challan(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->postJson('/app/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-1001',
                'challan_date' => '2026-06-01',
                'violation_type' => 'Over speeding',
                'amount' => 1500,
                'status' => 'UNPAID',
            ])
            ->assertCreated()
            ->assertJsonPath('data.challan_number', 'CH-1001')
            ->assertJsonPath('data.status', 'UNPAID');

        $this->actingAs($user)
            ->getJson('/app/api/challans?search=CH-1001')
            ->assertOk()
            ->assertJsonPath('data.0.challan_number', 'CH-1001')
            ->assertJsonPath('data.0.vehicle.registration_number', $vehicle->registration_number)
            ->assertJsonPath('data.0.driver.name', $driver->name);

        $this->assertDatabaseHas('challans', [
            'tenant_id' => $tenant->id,
            'challan_number' => 'CH-1001',
            'violation_type' => 'Over speeding',
        ]);
    }

    public function test_admin_cannot_delete_challan_without_delete_permission(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-DEL-1',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user)
            ->deleteJson("/app/api/challans/{$challan->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('challans', ['id' => $challan->id]);
    }

    public function test_super_admin_can_delete_challan(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('super_admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-DEL-2',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user)
            ->deleteJson("/app/api/challans/{$challan->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('challans', ['id' => $challan->id]);
    }

    public function test_admin_can_update_challan_payment_status(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-2002',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user)
            ->putJson("/app/api/challans/{$challan->id}", [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-2002',
                'challan_date' => '2026-05-15',
                'violation_type' => 'Signal jump',
                'amount' => 500,
                'status' => 'PAID',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Challan Tenant',
            'slug' => 'challan-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Challan User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role),
        ]);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB1234',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Driver',
            'phone' => '9876543210',
            'license_number' => 'DL-TEST',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => 1000,
            'is_active' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }
}
