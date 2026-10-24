<?php

namespace Tests\Feature\Api;

use App\Battery;
use App\BatteryInstallation;
use App\Driver;
use App\DriverAssignment;
use App\Enums\BatteryStatus;
use App\Enums\BatteryType;
use App\Enums\VehicleDocumentType;
use App\MaintenanceServiceType;
use App\Models\TenantSubscription;
use App\Support\BranchProvisioner;
use App\Support\MaintenanceServiceTypeDefaults;
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
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

        $this->assertNull($vehicle->deletedBy);

        $this->actingAs($admin)
            ->deleteJson("/app/api/vehicles/{$vehicle->id}", [
                'deleted_reason' => 'Decommissioned vehicle',
            ])
            ->assertNoContent();

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);

        $trashed = Vehicle::onlyTrashed()->find($vehicle->id);
        $this->assertEquals($admin->id, $trashed->deletedBy?->id);
        $this->assertEquals('Decommissioned vehicle', $trashed->deleted_reason);
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
            ->deleteJson("/app/api/vehicles/{$vehicle->id}", [
                'deleted_reason' => 'Decommissioned vehicle',
            ])
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
            ->deleteJson("/app/api/vehicles/{$vehicle->id}", [
                'deleted_reason' => 'Decommissioned vehicle',
            ])
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

    public function test_admin_creating_deleted_vehicle_creates_new_entry_and_leaves_archived(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->seedTenantLookups($tenant);

        $vehicle = $this->createVehicle($tenant, 'MH12AB1001');
        $vehicle->delete(); // Soft delete it

        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);

        $response = $this->actingAs($admin)
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

        $newVehicleId = $response->json('data.id');
        $this->assertNotEquals($vehicle->id, $newVehicleId);

        // Old vehicle is still soft-deleted in archive
        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);

        // New vehicle is active
        $this->assertDatabaseHas('vehicles', [
            'id' => $newVehicleId,
            'deleted_at' => null,
            'registration_number' => 'MH12AB1001',
        ]);

        // Attempting to restore the archived one is blocked because active exists
        $this->actingAs($admin)
            ->postJson("/app/api/vehicles/{$vehicle->id}/restore")
            ->assertStatus(422);
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

    public function test_user_can_view_vehicle_fuel_history_and_summary(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant, 'RJ27AB1234');
        $vehicle->update(['default_mileage_km_per_liter' => 4.0]);

        $driver = Driver::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'name' => 'Ramesh Kumar',
            'phone' => '9876543210',
            'license_number' => 'RJ2720200001234',
            'is_active' => true,
        ]);

        $trip = \App\Trip::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-1001',
            'status' => \App\Enums\TripStatus::Completed->value,
            'total_distance_km' => 400.0,
            'planned_mileage_km_per_liter' => 4.0,
        ]);

        \App\TripExpense::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'trip_id' => $trip->id,
            'expense_type' => \App\Enums\TripExpenseType::Diesel->value,
            'expense_date' => now()->toDateString(),
            'fuel_liters' => 100.0,
            'fuel_rate' => 95.0,
            'amount' => 9500.0,
            'fuel_odometer_km' => 1400.0,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/app/api/vehicles/{$vehicle->id}/fuel-history")
            ->assertOk();

        $response->assertJsonPath('summary.total_fuel_liters', 100)
            ->assertJsonPath('summary.total_fuel_cost', 9500)
            ->assertJsonPath('summary.total_distance_km', 400)
            ->assertJsonPath('summary.average_mileage', 4)
            ->assertJsonPath('summary.cost_per_km', 23.75)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip_number', 'TRIP-1001')
            ->assertJsonPath('data.0.fuel_liters', 100);
    }

    public function test_user_can_view_archived_vehicles_for_current_branch(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle1 = $this->createVehicle($tenant, 'RJ27AA1111');
        $this->createVehicle($tenant, 'RJ27AA2222');

        $vehicle1->delete();

        $response = $this->actingAs($admin)
            ->getJson('/app/api/vehicles?archived=archived')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_number', 'RJ27AA1111');
    }

    public function test_user_can_restore_archived_vehicle(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant, 'RJ27AA3333');
        $vehicle->delete();
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);

        $this->actingAs($admin)
            ->postJson("/app/api/vehicles/{$vehicle->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.registration_number', 'RJ27AA3333');

        $this->assertNotSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_archived_vehicle_resource_has_can_restore_flag(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant, 'RJ27AA5555');
        $vehicle->delete();

        $response = $this->actingAs($admin)
            ->getJson('/app/api/vehicles?archived=archived')
            ->assertOk();

        $response->assertJsonPath('data.0.can_restore', true);
    }

    public function test_vehicle_belongs_to_tenant(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->actingAs($admin);
        $vehicle = $this->createVehicle($tenant);

        $this->assertTrue($vehicle->tenant->is($tenant));
    }

    public function test_vehicle_has_many_documents(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->actingAs($admin);
        $vehicle = $this->createVehicle($tenant);

        $doc = VehicleDocument::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'document_type' => VehicleDocumentType::Insurance->value,
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $this->assertCount(1, $vehicle->documents);
        $this->assertTrue($vehicle->documents->first()->is($doc));
    }

    public function test_vehicle_has_many_maintenances(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->actingAs($admin);
        MaintenanceServiceTypeDefaults::seedForTenant($tenant->id);
        $serviceType = MaintenanceServiceType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();
        $vehicle = $this->createVehicle($tenant);

        $maintenance = VehicleMaintenance::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'service_date' => now()->toDateString(),
            'odometer_km' => 12000,
            'maintenance_service_type_id' => $serviceType->id,
            'service_provider' => 'Fleet Service Center',
            'cost_amount' => 5000,
        ]);

        $this->assertCount(1, $vehicle->maintenances);
        $this->assertTrue($vehicle->maintenances->first()->is($maintenance));
    }

    public function test_vehicle_has_many_trips(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->actingAs($admin);
        $vehicle = $this->createVehicle($tenant);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $vehicle->trips());
        $this->assertSame('vehicle_id', $vehicle->trips()->getForeignKeyName());
    }

    public function test_vehicle_tracks_active_batteries_and_complete_installation_history(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->actingAs($admin);
        $vehicle = $this->createVehicle($tenant);

        $activeBattery = Battery::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'serial_number' => 'BAT-ACTIVE-01',
            'brand' => 'Exide',
            'model' => 'XP100',
            'battery_type' => BatteryType::LeadAcid->value,
            'voltage' => '12V',
            'capacity_ah' => 150,
            'purchase_date' => now()->subMonths(2)->toDateString(),
            'purchase_cost' => 12000,
            'status' => BatteryStatus::Installed->value,
        ]);

        $removedBattery = Battery::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'serial_number' => 'BAT-REMOVED-02',
            'brand' => 'Amaron',
            'model' => 'AM150',
            'battery_type' => BatteryType::LeadAcid->value,
            'voltage' => '12V',
            'capacity_ah' => 150,
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 11000,
            'status' => BatteryStatus::InStock->value,
        ]);

        // Historical uninstalled battery
        BatteryInstallation::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'battery_id' => $removedBattery->id,
            'slot_name' => 'Slot 1',
            'installed_at' => now()->subMonths(6)->toDateString(),
            'removed_at' => now()->subMonths(2)->toDateString(),
            'km_at_installation' => 10000,
            'km_at_removal' => 15000,
        ]);

        // Current active battery
        $activeInstallation = BatteryInstallation::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'battery_id' => $activeBattery->id,
            'slot_name' => 'Slot 1',
            'installed_at' => now()->subMonths(2)->toDateString(),
            'removed_at' => null,
            'km_at_installation' => 15000,
        ]);

        // Complete historical audit log includes both
        $this->assertCount(2, $vehicle->batteryInstallations);

        // activeBatteries returns ONLY currently mounted battery (removed_at IS NULL)
        $activeBatteries = $vehicle->activeBatteries;
        $this->assertCount(1, $activeBatteries);
        $this->assertTrue($activeBatteries->first()->is($activeInstallation));

        // Verifies battery details are eager-loaded to prevent N+1 queries
        $this->assertTrue($activeBatteries->first()->relationLoaded('battery'));
        $this->assertEquals('BAT-ACTIVE-01', $activeBatteries->first()->battery->serial_number);
    }
}

