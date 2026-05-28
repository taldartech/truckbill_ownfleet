<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\Tenant;
use App\User;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_manager_can_create_driver_with_uuid_and_tenant_scope(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', [
                'name' => 'Ravi Kumar',
                'phone' => '9999999999',
                'license_number' => 'DL-12345',
                'license_expiry_date' => now()->addMonths(5)->toDateString(),
                'daily_wage' => 1250.75,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Ravi Kumar');

        $driverId = $response->json('data.id');
        $this->assertIsString($driverId);
        $this->assertDatabaseHas('drivers', [
            'id' => $driverId,
            'tenant_id' => $tenant->id,
            'name' => 'Ravi Kumar',
        ]);
    }

    public function test_tenant_isolation_blocks_cross_tenant_driver_access(): void
    {
        [$tenantA, $userA] = $this->createTenantUser('FLEET_MANAGER');
        [$tenantB] = $this->createTenantUser('FLEET_MANAGER');

        $driverB = Driver::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Driver',
            'license_number' => 'B-1',
            'daily_wage' => 700,
            'is_active' => true,
        ]);

        Sanctum::actingAs($userA);

        $response = $this->withHeader('X-Tenant-Id', $tenantA->id)
            ->getJson("/api/drivers/{$driverB->id}");

        $response->assertNotFound();
    }

    public function test_attendance_marking_sets_wage_owner_driver_and_wage_amount(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary Driver', 950.00);

        Sanctum::actingAs($user);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'PRESENT',
            'wage_amount' => 950,
        ];

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.wage_owner_driver_id', $driver->id);
        $response->assertJsonPath('data.wage_amount', '950.00');

        $this->assertDatabaseHas('attendances', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
        ]);
    }

    public function test_license_expiring_endpoint_returns_drivers_within_days_filter(): void
    {
        [$tenant, $user] = $this->createTenantUser('ACCOUNTANT');
        Sanctum::actingAs($user);

        $expiringSoon = $this->createDriver($tenant, 'Soon Expiring', 800, now()->addDays(10)->toDateString());
        $this->createDriver($tenant, 'Far Expiring', 850, now()->addDays(120)->toDateString());

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/drivers/license-expiring?days=30');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $expiringSoon->id]);
        $response->assertJsonMissing(['name' => 'Far Expiring']);
    }

    public function test_driver_role_cannot_create_or_update_driver_records(): void
    {
        [$tenant, $driverUser] = $this->createTenantUser('DRIVER');
        $driver = $this->createDriver($tenant, 'Editable Driver', 600);

        Sanctum::actingAs($driverUser);

        $createResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', [
                'name' => 'Blocked Driver',
                'daily_wage' => 500,
            ]);

        $updateResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/drivers/{$driver->id}", [
                'name' => 'Should Not Update',
            ]);

        $createResponse->assertForbidden();
        $updateResponse->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
        ]);

        return [$tenant, $user];
    }

    private function createVehicle(Tenant $tenant): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => strtoupper(fake()->bothify('??##??####')),
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
        ]);
    }

    private function createDriver(Tenant $tenant, string $name, float $dailyWage, ?string $expiry = null): Driver
    {
        return Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => fake()->numerify('9#########'),
            'license_number' => strtoupper(fake()->bothify('DL-#####')),
            'license_expiry_date' => $expiry ?? now()->addYear()->toDateString(),
            'daily_wage' => $dailyWage,
            'is_active' => true,
        ]);
    }
}
