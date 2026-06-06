<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\DriverAssignment;
use App\Support\TenantContext;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_web_session_can_create_driver_via_stateful_api(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $response = $this
            ->withHeader('Referer', 'http://127.0.0.1:8001/app/dashboard')
            ->postJson('/app/api/drivers', [
                'name' => 'Session Driver',
                'phone' => '9888888888',
                'license_number' => 'DL-SESSION',
                'joining_date' => '2024-01-15',
                'daily_wage' => 900,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Session Driver');

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenant->id,
            'name' => 'Session Driver',
        ]);
    }

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
                'joining_date' => '2024-01-15',
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

    public function test_driver_index_supports_pagination_and_search(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        Sanctum::actingAs($user);

        $this->createDriver($tenant, 'Alpha Driver', 800);
        $this->createDriver($tenant, 'Beta Driver', 850, null);
        TenantContext::setTenantId($tenant->id);
        Driver::query()->where('name', 'Beta Driver')->update([
            'phone' => '9111222333',
        ]);
        TenantContext::setTenantId(null);

        $paginated = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/drivers?per_page=1&page=1');

        $paginated->assertOk();
        $paginated->assertJsonPath('meta.per_page', 1);
        $paginated->assertJsonPath('meta.total', 2);

        $search = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/drivers?search=9111222333');

        $search->assertOk();
        $search->assertJsonCount(1, 'data');
        $search->assertJsonPath('data.0.name', 'Beta Driver');
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
        $primary = $this->createDriver($tenant, 'Primary Driver', 950.00);
        $substitute = $this->createDriver($tenant, 'Substitute Driver', 900.00);

        Sanctum::actingAs($user);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $primary->id,
            'temporary_driver_id' => $substitute->id,
            'attendance_date' => '2026-06-15',
            'status' => 'ABSENT',
        ];

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.wage_owner_driver_id', $substitute->id);
        $response->assertJsonPath('data.wage_amount', '30.00');

        $this->assertDatabaseHas('attendances', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $primary->id,
            'wage_owner_driver_id' => $substitute->id,
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

    public function test_driver_update_accepts_numeric_phone_from_json_clients(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $driver = $this->createDriver($tenant, 'Driver Mobile', 500);

        $this->actingAs($user)
            ->putJson("/app/api/drivers/{$driver->id}", [
                'phone' => 9766554433,
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '9766554433');
    }

    public function test_driver_deactivation_closes_open_primary_assignments(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $driver = $this->createDriver($tenant, 'Driver Leaving', 900);
        $vehicle = $this->createVehicle($tenant);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subDays(10)->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/drivers/{$driver->id}", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('driver_assignments', [
            'driver_id' => $driver->id,
            'assigned_to' => now()->toDateString(),
        ]);
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
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
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
