<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\DriverAssignment;
use App\Support\SelectedCompanySession;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_toggle_vehicle_status_but_cannot_delete(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        $this->actingAs($admin)
            ->patchJson("/app/api/vehicles/{$vehicle->id}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin)
            ->deleteJson("/app/api/vehicles/{$vehicle->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'deleted_at' => null,
        ]);
    }

    public function test_moderator_cannot_toggle_vehicle_status(): void
    {
        [$tenant, $moderator] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);

        $this->actingAs($moderator)
            ->patchJson("/app/api/vehicles/{$vehicle->id}/status", [
                'is_active' => false,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_soft_delete_vehicle(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Fleet Co');
        $vehicle = $this->createVehicle($tenant);

        SelectedCompanySession::set($tenant->id);

        $this->actingAs($superAdmin)
            ->deleteJson("/app/api/vehicles/{$vehicle->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);
    }

    public function test_vehicle_index_can_filter_active_only(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $activeVehicle = $this->createVehicle($tenant, 'MH01AA1111', true);
        $this->createVehicle($tenant, 'MH01BB2222', false);

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?active_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', $activeVehicle->registration_number);
    }

    public function test_vehicle_index_can_filter_by_primary_driver_assignment(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $assignedVehicle = $this->createVehicle($tenant, 'MH01ASSIGNED');
        $unassignedVehicle = $this->createVehicle($tenant, 'MH01UNASSIGNED');
        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Primary Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-PRIMARY',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => 700,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $assignedVehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?primary_driver=assigned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', $assignedVehicle->registration_number);

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?primary_driver=unassigned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', $unassignedVehicle->registration_number);
    }

    public function test_admin_cannot_create_vehicle_with_duplicate_registration_number(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        TyreLayoutDefaults::seedForTenant($tenant->id);
        $this->createVehicle($tenant, 'MH12AB1001');

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'mh12ab1001',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number'])
            ->assertJsonPath(
                'errors.registration_number.0',
                'This registration number is already in use.',
            );
    }

    public function test_admin_cannot_update_vehicle_to_duplicate_registration_number(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        TyreLayoutDefaults::seedForTenant($tenant->id);
        $this->createVehicle($tenant, 'MH12AB1001');
        $otherVehicle = $this->createVehicle($tenant, 'MH12AB2002');

        $this->actingAs($admin)
            ->putJson("/app/api/vehicles/{$otherVehicle->id}", [
                'registration_number' => 'MH12AB1001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number'])
            ->assertJsonPath(
                'errors.registration_number.0',
                'This registration number is already in use.',
            );
    }

    public function test_vehicle_index_can_filter_by_search_type_and_status(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->createVehicle($tenant, 'MH01TRUCK1', true, 'TRUCK');
        $this->createVehicle($tenant, 'MH01TANKER1', true, 'TANKER');
        $this->createVehicle($tenant, 'MH01TRAILER1', false, 'TRAILER');

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?search=MH01TANKER1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle_type', 'TANKER');

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?vehicle_type=TRUCK')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', 'MH01TRUCK1');

        $this->actingAs($admin)
            ->getJson('/app/api/vehicles?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', 'MH01TRAILER1');
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

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createOwnedTenant(User $owner, string $name): Tenant
    {
        return Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    private function createVehicle(
        Tenant $tenant,
        ?string $registration = null,
        bool $isActive = true,
        string $vehicleType = 'TRUCK',
    ): Vehicle {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => $registration ?? strtoupper(fake()->bothify('??##??####')),
            'vehicle_type' => $vehicleType,
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
            'is_active' => $isActive,
        ]);
    }
}
