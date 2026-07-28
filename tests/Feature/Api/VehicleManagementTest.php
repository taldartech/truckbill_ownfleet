<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\DriverAssignment;
use App\Models\TenantSubscription;
use App\Support\BranchProvisioner;
use App\Support\TruckTypeDefaults;
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

    public function test_admin_can_toggle_vehicle_status_and_delete(): void
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
            ->assertNoContent();

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
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

    public function test_moderator_can_create_vehicle_but_not_update(): void
    {
        [$tenant, $moderator] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);

        $this->actingAs($moderator)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ99ZZ9999',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
                'make' => 'Tata',
                'model' => 'LPT',
                'odometer_km' => 0,
            ])
            ->assertCreated();

        $this->actingAs($moderator)
            ->putJson("/app/api/vehicles/{$vehicle->id}", [
                'make' => 'Should Not Update',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_soft_delete_vehicle(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Fleet Co');
        $branch = BranchProvisioner::ensureDefaultBranch($tenant);
        $this->enterBranchForSuperAdmin($superAdmin, $branch);
        $vehicle = $this->createVehicle($tenant);

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

    public function test_vehicle_creation_is_blocked_when_subscription_vehicle_limit_reached(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
        $tenant->subscription->update([
            'included_trucks' => 1,
            'addon_trucks' => 0,
        ]);

        $this->createVehicle($tenant, 'RJ27AV5001');

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5002',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number'])
            ->assertJsonPath(
                'errors.registration_number.0',
                'Vehicle limit reached (1/1). Purchase a vehicle add-on from Subscription → Add-ons.',
            );
    }

    public function test_deleting_a_vehicle_frees_a_subscription_truck_slot(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
        $tenant->subscription->update([
            'included_trucks' => 1,
            'addon_trucks' => 0,
        ]);

        $vehicle = $this->createVehicle($tenant, 'RJ27AV5001');

        $this->actingAs($admin)
            ->deleteJson("/app/api/vehicles/{$vehicle->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5002',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'RJ27AV5002');
    }

    public function test_admin_can_create_vehicle_with_lowercase_registration_normalized(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'rj27av5055',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'RJ27AV5055');

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'RJ27AV5055',
        ]);
    }

    public function test_admin_cannot_create_vehicle_with_invalid_registration_number(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'INVALID123',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_number'])
            ->assertJsonPath(
                'errors.registration_number.0',
                'Please enter a valid vehicle registration number (e.g. RJ27AV5055).',
            );
    }

    public function test_admin_cannot_create_vehicle_without_tyre_layout(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5055',
                'vehicle_type' => 'TRUCK',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tyre_layout']);
    }

    public function test_admin_cannot_create_vehicle_with_duplicate_registration_number(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
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

    public function test_admin_can_recreate_soft_deleted_vehicle_by_restoring_it(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
        
        $vehicle = $this->createVehicle($tenant, 'MH12AB1001');
        $vehicle->delete(); // Soft delete it
        
        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);
        
        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'mh12ab1001',
                'vehicle_type' => 'TANKER',
                'tyre_layout' => 'TYRES_10',
                'make' => 'Tata Restore',
                'model' => 'LPT Restore',
                'odometer_km' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'MH12AB1001')
            ->assertJsonPath('data.vehicle_type', 'TANKER')
            ->assertJsonPath('data.make', 'Tata Restore')
            ->assertJsonPath('data.model', 'LPT Restore');
            
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'deleted_at' => null,
            'vehicle_type' => 'TANKER',
            'make' => 'Tata Restore',
            'model' => 'LPT Restore',
            'odometer_km' => 500.00,
        ]);
    }

    public function test_admin_cannot_update_vehicle_to_duplicate_registration_number(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
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

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $this->assignBranchIfRequired($user, $tenant)];
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
        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        return $tenant;
    }

    private function seedTenantLookups(Tenant $tenant): void
    {
        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);
    }

    public function test_admin_can_create_vehicle_with_default_mileage_and_max_load(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5055',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
                'default_mileage_km_per_liter' => 2.5,
                'max_load_ton' => 22,
                'fuel_tank_capacity_ltr' => 350.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.default_mileage_km_per_liter', '2.50')
            ->assertJsonPath('data.max_load_ton', '22.000')
            ->assertJsonPath('data.fuel_tank_capacity_ltr', '350.50');
    }

    public function test_admin_can_update_vehicle_fuel_tank_capacity(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);
        $vehicle = $this->createVehicle($tenant);

        $this->actingAs($admin)
            ->putJson("/app/api/vehicles/{$vehicle->id}", [
                'fuel_tank_capacity_ltr' => 400,
            ])
            ->assertOk()
            ->assertJsonPath('data.fuel_tank_capacity_ltr', '400.00');
    }

    public function test_admin_cannot_create_vehicle_with_future_manufacture_year(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5056',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
                'manufacture_year' => (int) date('Y') + 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['manufacture_year']);
    }

    public function test_admin_can_create_vehicle_with_current_manufacture_year(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $this->actingAs($admin)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'RJ27AV5057',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
                'manufacture_year' => (int) date('Y'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.manufacture_year', (int) date('Y'));
    }

    private function createVehicle(
        Tenant $tenant,
        ?string $registration = null,
        bool $isActive = true,
        string $vehicleType = 'TRUCK',
    ): Vehicle {
        $this->seedTenantLookups($tenant);

        return Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => $registration ?? strtoupper(fake()->bothify('??##??####')),
            'vehicle_type' => $vehicleType,
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
            'is_active' => $isActive,
        ]);
    }
}
