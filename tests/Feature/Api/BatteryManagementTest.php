<?php

namespace Tests\Feature\Api;

use App\Battery;
use App\BatteryInstallation;
use App\BatteryReplacement;
use App\Enums\BatteryRemovalReason;
use App\Enums\BatteryStatus;
use App\Enums\BatteryType;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BatteryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_battery(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $response = $this->batteryApi($tenant)
            ->postJson('/api/batteries', [
                'serial_number' => 'BAT-00125',
                'brand' => 'Exide',
                'model' => 'Mileage',
                'battery_type' => BatteryType::LeadAcid->value,
                'voltage' => '12V',
                'capacity_ah' => 150,
                'purchase_date' => '2026-09-15',
                'purchase_cost' => 14500,
                'warranty_months' => 24,
                'expected_life_months' => 36,
                'supplier_name' => 'Auto Power Traders',
                'notes' => 'Initial purchase for fleet',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.serial_number', 'BAT-00125');
        $response->assertJsonPath('data.brand', 'Exide');
        $response->assertJsonPath('data.model', 'Mileage');
        $response->assertJsonPath('data.voltage', '12V');
        $response->assertJsonPath('data.capacity_ah', 150);
        $response->assertJsonPath('data.status', BatteryStatus::InStock->value);
        $response->assertJsonPath('data.warranty_months', 24);
        $response->assertJsonPath('data.warranty_valid_until', '2028-09-15');
        $response->assertJsonPath('data.is_in_warranty', true);

        $this->assertDatabaseHas('batteries', [
            'tenant_id' => $tenant->id,
            'serial_number' => 'BAT-00125',
            'brand' => 'Exide',
            'status' => BatteryStatus::InStock->value,
        ]);
    }

    public function test_serial_number_must_be_unique_per_tenant(): void
    {
        [$tenantA, $userA] = $this->createFixtures('admin');
        [$tenantB, $userB] = $this->createFixtures('admin');

        Sanctum::actingAs($userA);

        $this->batteryApi($tenantA)
            ->postJson('/api/batteries', $this->validBatteryPayload([
                'serial_number' => 'BAT-DUP-01',
            ]))
            ->assertCreated();

        // Duplicate within same tenant fails
        $this->batteryApi($tenantA)
            ->postJson('/api/batteries', $this->validBatteryPayload([
                'serial_number' => 'BAT-DUP-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);

        // Same serial number in another tenant succeeds
        Sanctum::actingAs($userB);
        $this->batteryApi($tenantB)
            ->postJson('/api/batteries', $this->validBatteryPayload([
                'serial_number' => 'BAT-DUP-01',
            ]))
            ->assertCreated();
    }

    public function test_admin_can_view_battery_list_with_filters_and_search(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $this->createBattery($tenant, ['serial_number' => 'EXIDE-101', 'brand' => 'Exide', 'status' => BatteryStatus::InStock]);
        $this->createBattery($tenant, ['serial_number' => 'AMARON-202', 'brand' => 'Amaron', 'status' => BatteryStatus::InStock]);
        $this->createBattery($tenant, ['serial_number' => 'EXIDE-303', 'brand' => 'Exide', 'status' => BatteryStatus::Installed]);

        // Filter by brand
        $response = $this->batteryApi($tenant)
            ->getJson('/api/batteries?brand=Exide');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Filter by status
        $responseStatus = $this->batteryApi($tenant)
            ->getJson('/api/batteries?status=' . BatteryStatus::Installed->value);
        $responseStatus->assertOk();
        $responseStatus->assertJsonCount(1, 'data');
        $responseStatus->assertJsonPath('data.0.serial_number', 'EXIDE-303');

        // Search by serial
        $responseSearch = $this->batteryApi($tenant)
            ->getJson('/api/batteries?search=202');
        $responseSearch->assertOk();
        $responseSearch->assertJsonCount(1, 'data');
        $responseSearch->assertJsonPath('data.0.serial_number', 'AMARON-202');
    }

    public function test_admin_can_view_battery_counts(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $this->createBattery($tenant, ['serial_number' => 'BAT-C1', 'status' => BatteryStatus::InStock]);
        $this->createBattery($tenant, ['serial_number' => 'BAT-C2', 'status' => BatteryStatus::InStock]);
        $this->createBattery($tenant, ['serial_number' => 'BAT-C3', 'status' => BatteryStatus::Installed]);
        $this->createBattery($tenant, ['serial_number' => 'BAT-C4', 'status' => BatteryStatus::Scrapped]);

        $response = $this->batteryApi($tenant)->getJson('/app/api/batteries/counts');
        $response->assertOk();
        $response->assertJsonPath('counts.total', 4);
        $response->assertJsonPath('counts.in_stock', 2);
        $response->assertJsonPath('counts.installed', 1);
        $response->assertJsonPath('counts.scrapped', 1);
    }

    public function test_admin_can_view_in_stock_batteries_for_dropdown(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $this->createBattery($tenant, ['serial_number' => 'BAT-STOCK-1', 'status' => BatteryStatus::InStock]);
        $this->createBattery($tenant, ['serial_number' => 'BAT-INSTALLED', 'status' => BatteryStatus::Installed]);

        $response = $this->batteryApi($tenant)->getJson('/app/api/batteries/in-stock');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.serial_number', 'BAT-STOCK-1');
    }

    public function test_admin_can_view_battery_details_and_lifecycle(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-LIFE-01',
            'brand' => 'Tata Green',
            'purchase_date' => '2025-01-01',
            'warranty_months' => 24,
            'warranty_valid_until' => '2027-01-01',
            'status' => BatteryStatus::Installed,
        ]);

        $operational = $this->operationalAttributesForTenant($tenant);
        BatteryInstallation::query()->create(array_merge($operational, [
            'battery_id' => $battery->id,
            'vehicle_id' => $vehicle->id,
            'slot_name' => 'Main #1',
            'installed_at' => '2025-02-01',
            'km_at_installation' => 1000,
        ]));

        $response = $this->batteryApi($tenant)->getJson("/api/batteries/{$battery->id}");
        $response->assertOk();
        $response->assertJsonPath('data.serial_number', 'BAT-LIFE-01');
        $response->assertJsonPath('data.brand', 'Tata Green');
        $response->assertJsonPath('data.active_installation.vehicle_registration', $vehicle->registration_number);
        $response->assertJsonPath('data.active_installation.slot_name', 'Main #1');
        $response->assertJsonPath('life_summary.installations_count', 1);
        $response->assertJsonPath('life_summary.current_vehicle.registration_number', $vehicle->registration_number);
    }

    public function test_admin_can_update_battery(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-UPD-01',
            'brand' => 'Exide',
            'model' => 'Old Model',
        ]);

        $response = $this->batteryApi($tenant)
            ->putJson("/api/batteries/{$battery->id}", [
                'model' => 'Mileage Plus',
                'notes' => 'Updated after battery test',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.model', 'Mileage Plus');
        $response->assertJsonPath('data.notes', 'Updated after battery test');

        $this->assertDatabaseHas('batteries', [
            'id' => $battery->id,
            'model' => 'Mileage Plus',
        ]);
    }

    public function test_admin_can_scrap_battery_in_stock(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-SCRAP-01',
            'status' => BatteryStatus::InStock,
        ]);

        $response = $this->batteryApi($tenant)
            ->postJson("/app/api/batteries/{$battery->id}/scrap", [
                'scrapped_at' => '2026-09-30',
                'scrap_notes' => 'Dead cells, non-rechargeable',
                'scrap_sale_amount' => 1200,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', BatteryStatus::Scrapped->value);
        $response->assertJsonPath('data.scrap_sale_amount', 1200);

        $this->assertDatabaseHas('batteries', [
            'id' => $battery->id,
            'status' => BatteryStatus::Scrapped->value,
            'scrap_sale_amount' => 1200,
        ]);
    }

    public function test_admin_cannot_scrap_installed_battery(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-INST-SCRAP',
            'status' => BatteryStatus::Installed,
        ]);

        $operational = $this->operationalAttributesForTenant($tenant);
        BatteryInstallation::query()->create(array_merge($operational, [
            'battery_id' => $battery->id,
            'vehicle_id' => $vehicle->id,
            'slot_name' => 'Main #1',
            'installed_at' => '2026-01-01',
            'km_at_installation' => 1000,
        ]));

        $response = $this->batteryApi($tenant)
            ->postJson("/app/api/batteries/{$battery->id}/scrap", [
                'scrap_sale_amount' => 1000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['battery']);
    }

    public function test_admin_can_delete_battery_in_stock(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-DEL-01',
            'status' => BatteryStatus::InStock,
        ]);

        $response = $this->batteryApi($tenant)
            ->deleteJson("/api/batteries/{$battery->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('batteries', [
            'id' => $battery->id,
        ]);
    }

    public function test_tenant_isolation_prevents_access(): void
    {
        [$tenantA, $userA] = $this->createFixtures('admin');
        [$tenantB, $userB] = $this->createFixtures('admin');

        $batteryA = $this->createBattery($tenantA, ['serial_number' => 'BAT-TENANT-A']);

        Sanctum::actingAs($userB);

        // Cannot view tenant A's battery
        $this->batteryApi($tenantB)
            ->getJson("/api/batteries/{$batteryA->id}")
            ->assertNotFound();

        // Cannot update tenant A's battery
        $this->batteryApi($tenantB)
            ->putJson("/api/batteries/{$batteryA->id}", [
                'model' => 'Hacked',
            ])
            ->assertNotFound();

        // Cannot delete tenant A's battery
        $this->batteryApi($tenantB)
            ->deleteJson("/api/batteries/{$batteryA->id}")
            ->assertNotFound();
    }

    public function test_admin_can_install_battery_on_vehicle(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-00125',
            'purchase_date' => '2026-09-15',
            'status' => BatteryStatus::InStock,
        ]);

        $response = $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Main #1',
                'installed_at' => '2026-09-15',
                'km_at_installation' => 5000,
                'notes' => 'Installed new battery on truck',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slot_name', 'Main #1');
        $response->assertJsonPath('data.vehicle_id', $vehicle->id);
        $response->assertJsonPath('data.km_at_installation', 5000);

        $this->assertDatabaseHas('batteries', [
            'id' => $battery->id,
            'status' => BatteryStatus::Installed->value,
        ]);

        $this->assertDatabaseHas('battery_installations', [
            'battery_id' => $battery->id,
            'vehicle_id' => $vehicle->id,
            'slot_name' => 'Main #1',
            'km_at_installation' => 5000,
            'removed_at' => null,
        ]);
    }

    public function test_multi_battery_slots_supported_on_same_vehicle(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery1 = $this->createBattery($tenant, ['serial_number' => 'BAT-SLOT-1']);
        $battery2 = $this->createBattery($tenant, ['serial_number' => 'BAT-SLOT-2']);

        // Install Battery 1 in Slot 1
        $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery1->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Slot 1 (Main)',
                'installed_at' => '2026-09-20',
                'km_at_installation' => 5000,
            ])
            ->assertCreated();

        // Install Battery 2 in Slot 2
        $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery2->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Slot 2 (Auxiliary)',
                'installed_at' => '2026-09-20',
                'km_at_installation' => 5000,
            ])
            ->assertCreated();

        // Vehicle query returns both active batteries
        $response = $this->batteryApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/batteries");

        $response->assertOk();
        $response->assertJsonCount(2, 'active_batteries');
        $this->assertEquals(BatteryStatus::Installed, $battery1->fresh()->status);
        $this->assertEquals(BatteryStatus::Installed, $battery2->fresh()->status);
    }

    public function test_cannot_install_battery_into_already_occupied_slot(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery1 = $this->createBattery($tenant, ['serial_number' => 'BAT-OCC-1']);
        $battery2 = $this->createBattery($tenant, ['serial_number' => 'BAT-OCC-2']);

        $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery1->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Main #1',
                'installed_at' => '2026-09-20',
            ])
            ->assertCreated();

        // Attempting to install battery2 in same slot throws error
        $response = $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery2->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Main #1',
                'installed_at' => '2026-09-20',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slot_name']);
    }

    public function test_cannot_install_battery_before_its_purchase_date(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, [
            'purchase_date' => '2026-09-15',
        ]);

        $response = $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery->id}/install", [
                'vehicle_id' => $vehicle->id,
                'installed_at' => '2026-09-10',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['installed_at']);
    }

    public function test_battery_replacement_transaction_atomic_workflow(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        // 1. Initial battery installed on vehicle
        $oldBattery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-00125',
            'brand' => 'Exide',
            'model' => 'Mileage',
            'purchase_date' => '2026-09-15',
        ]);

        $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$oldBattery->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Main #1',
                'installed_at' => '2026-09-15',
                'km_at_installation' => 170000,
            ])
            ->assertCreated();

        // 2. New in-stock battery
        $newBattery = $this->createBattery($tenant, [
            'serial_number' => 'BAT-00182',
            'brand' => 'Exide',
            'model' => 'Mileage',
            'purchase_date' => '2026-09-25',
            'status' => BatteryStatus::InStock,
        ]);

        // 3. Perform Replacement
        $response = $this->batteryApi($tenant)
            ->postJson('/api/batteries/replace', [
                'vehicle_id' => $vehicle->id,
                'old_battery_id' => $oldBattery->id,
                'new_battery_id' => $newBattery->id,
                'slot_name' => 'Main #1',
                'replaced_at' => '2026-09-30',
                'odometer_km' => 182450,
                'reason' => BatteryRemovalReason::BatteryFailed->value,
                'notes' => 'Old battery failed internal cell test, replaced with new under warranty claim',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slot_name', 'Main #1');
        $response->assertJsonPath('data.old_battery.serial_number', 'BAT-00125');
        $response->assertJsonPath('data.new_battery.serial_number', 'BAT-00182');
        $response->assertJsonPath('data.odometer_km', 182450);
        $response->assertJsonPath('data.reason', BatteryRemovalReason::BatteryFailed->value);

        // Verify Old Battery: status is REMOVED
        $this->assertDatabaseHas('batteries', [
            'id' => $oldBattery->id,
            'status' => BatteryStatus::Removed->value,
        ]);

        // Verify Old Installation closed with running km
        $this->assertDatabaseHas('battery_installations', [
            'battery_id' => $oldBattery->id,
            'vehicle_id' => $vehicle->id,
            'km_at_removal' => 182450,
            'running_km' => 12450, // 182450 - 170000
            'removal_reason' => BatteryRemovalReason::BatteryFailed->value,
        ]);

        // Verify New Battery: status is INSTALLED
        $this->assertDatabaseHas('batteries', [
            'id' => $newBattery->id,
            'status' => BatteryStatus::Installed->value,
        ]);

        // Verify New Installation created
        $this->assertDatabaseHas('battery_installations', [
            'battery_id' => $newBattery->id,
            'vehicle_id' => $vehicle->id,
            'slot_name' => 'Main #1',
            'km_at_installation' => 182450,
            'removed_at' => null,
        ]);

        // Verify Replacement Log created
        $this->assertDatabaseHas('battery_replacements', [
            'vehicle_id' => $vehicle->id,
            'old_battery_id' => $oldBattery->id,
            'new_battery_id' => $newBattery->id,
            'slot_name' => 'Main #1',
            'odometer_km' => 182450,
            'reason' => BatteryRemovalReason::BatteryFailed->value,
        ]);

        // Verify Vehicle Odometer updated
        $this->assertEquals(182450, (float) $vehicle->fresh()->odometer_km);
    }

    public function test_admin_can_remove_battery_without_immediate_replacement(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, ['serial_number' => 'BAT-REM-01']);

        $installResponse = $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Slot 1',
                'installed_at' => '2026-09-01',
                'km_at_installation' => 10000,
            ]);

        $installationId = $installResponse->json('data.id');

        // Remove battery
        $removeResponse = $this->batteryApi($tenant)
            ->postJson("/api/battery-installations/{$installationId}/remove", [
                'removed_at' => '2026-09-20',
                'odometer_km' => 12500,
                'removal_reason' => BatteryRemovalReason::VehicleMaintenance->value,
                'notes' => 'Removed for alternator diagnostics',
            ]);

        $removeResponse->assertOk();
        $removeResponse->assertJsonPath('data.removed_at', '2026-09-20');
        $removeResponse->assertJsonPath('data.running_km', 2500);

        $this->assertDatabaseHas('batteries', [
            'id' => $battery->id,
            'status' => BatteryStatus::Removed->value,
        ]);

        // Slot is now free
        $this->assertDatabaseMissing('battery_installations', [
            'vehicle_id' => $vehicle->id,
            'slot_name' => 'Slot 1',
            'removed_at' => null,
        ]);
    }

    public function test_vehicle_batteries_endpoint_returns_active_history_and_replacements(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $battery = $this->createBattery($tenant, ['serial_number' => 'BAT-VEH-HIST']);

        $this->batteryApi($tenant)
            ->postJson("/api/batteries/{$battery->id}/install", [
                'vehicle_id' => $vehicle->id,
                'slot_name' => 'Main #1',
                'installed_at' => '2026-09-01',
                'km_at_installation' => 5000,
            ]);

        $response = $this->batteryApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/batteries");

        $response->assertOk();
        $response->assertJsonPath('vehicle.id', $vehicle->id);
        $response->assertJsonPath('vehicle.registration_number', $vehicle->registration_number);
        $response->assertJsonCount(1, 'active_batteries');
        $response->assertJsonPath('active_batteries.0.slot_name', 'Main #1');
    }

    private function batteryApi(Tenant $tenant): static
    {
        $branchId = $this->operationalAttributesForTenant($tenant)['branch_id'];

        return $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validBatteryPayload(array $overrides = []): array
    {
        return array_merge([
            'serial_number' => 'BAT-' . fake()->unique()->numerify('#####'),
            'brand' => 'Exide',
            'model' => 'PowerSafe',
            'battery_type' => BatteryType::LeadAcid->value,
            'voltage' => '12V',
            'capacity_ah' => 150,
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 15000,
            'warranty_months' => 24,
            'expected_life_months' => 36,
            'supplier_name' => 'Exide Dealers Ltd',
        ], $overrides);
    }

    private function createBattery(Tenant $tenant, array $attributes = []): Battery
    {
        $operational = $this->operationalAttributesForTenant($tenant);

        return Battery::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'branch_id' => $operational['branch_id'],
            'serial_number' => 'BAT-' . fake()->unique()->numerify('#####'),
            'brand' => 'Exide',
            'model' => 'Mileage',
            'battery_type' => BatteryType::LeadAcid,
            'voltage' => '12V',
            'capacity_ah' => 150,
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 14000,
            'warranty_months' => 24,
            'warranty_valid_until' => '2028-01-15',
            'expected_life_months' => 36,
            'status' => BatteryStatus::InStock,
        ], $attributes));
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Battery Tenant',
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

        $user = $this->assignBranchIfRequired($user, $tenant);
        $operational = $this->operationalAttributesForTenant($tenant);

        $vehicle = Vehicle::query()->create(array_merge($operational, [
            'registration_number' => 'MH12BT' . fake()->unique()->numerify('####'),
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 5000,
        ]));

        return [$tenant, $user, $vehicle];
    }
}
