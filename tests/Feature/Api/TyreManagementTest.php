<?php

namespace Tests\Feature\Api;

use App\Enums\TyrePosition;
use App\Enums\TyreStatus;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Tyre;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TyreManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_and_install_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-001',
                'brand' => 'MRF',
                'size' => '10.00-20',
                'purchase_date' => '2026-01-15',
                'purchase_cost' => 25000,
                'tyre_type' => 'RADIAL',
            ]);

        $create->assertOk();
        $create->assertJsonPath('data.serial_number', 'TYRE-001');
        $create->assertJsonPath('data.status', TyreStatus::InStock->value);

        $tyreId = $create->json('data.id');

        $install = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-02-01',
                'km_at_installation' => 1000,
            ]);

        $install->assertCreated();
        $install->assertJsonPath('data.position', TyrePosition::FrontLeft->value);

        $this->assertDatabaseHas('tyres', [
            'id' => $tyreId,
            'status' => TyreStatus::Installed->value,
        ]);
    }

    public function test_tyre_life_report_returns_data_for_tenant(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-LIFE-1',
            'brand' => 'CEAT',
            'purchase_cost' => 10000,
            'status' => TyreStatus::InStock,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/tyres/life');

        $response->assertOk();
        $response->assertJsonFragment(['serial_number' => 'TYRE-LIFE-1']);
    }

    public function test_vehicle_tyre_positions_returns_all_slots(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-positions");

        $response->assertOk();
        $response->assertOk();
        $response->assertJsonCount(7, 'data');
        $response->assertJsonPath('data.0.position_label', 'Front Left');
    }

    public function test_vehicle_tyre_positions_respects_tyre_layout_with_multiple_spares(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        $vehicle->update(['tyre_layout' => 'TYRES_12']);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-positions");

        $response->assertOk();
        $response->assertJsonCount(12, 'data');
        $response->assertJsonFragment(['position' => 'SPARE_1', 'position_label' => 'Spare 1']);
        $response->assertJsonFragment(['position' => 'SPARE_2', 'position_label' => 'Spare 2']);
        $response->assertJsonFragment(['position' => 'AX2_L_OUT', 'position_label' => 'Axle 2 — Left (Outer)']);
    }

    public function test_vehicle_tyre_history_returns_summary_and_installation_log(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $activeTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-ACTIVE', TyrePosition::FrontLeft->value);

        $removedTyreCreate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-REMOVED',
                'purchase_cost' => 20000,
            ]);
        $removedTyreId = $removedTyreCreate->json('data.id');

        $removedInstall = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$removedTyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontRight->value,
                'installed_at' => '2026-01-01',
                'km_at_installation' => 1000,
            ]);
        $removedInstallationId = $removedInstall->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyre-installations/{$removedInstallationId}/remove", [
                'removed_at' => '2026-03-01',
                'stint_km' => 2500,
            ])
            ->assertOk();

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-history");

        $response->assertOk();
        $response->assertJsonPath('summary.unique_tyres_used', 2);
        $response->assertJsonPath('summary.total_stints', 2);
        $response->assertJsonPath('summary.currently_installed', 1);
        $response->assertJsonPath('summary.total_km_on_truck', 6500);
        $response->assertJsonCount(2, 'history');
        $response->assertJsonFragment([
            'tyre_id' => $activeTyreId,
            'tyre_serial_number' => 'TYRE-ACTIVE',
            'is_active' => true,
        ]);
        $response->assertJsonFragment([
            'tyre_id' => $removedTyreId,
            'tyre_serial_number' => 'TYRE-REMOVED',
            'is_active' => false,
            'stint_km' => 2500,
        ]);
    }

    public function test_tyre_timeline_returns_newest_events_first_in_logical_order(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicleB = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TY9997',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 15000,
        ]);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicleA, 'TYRE-TIMELINE', TyrePosition::FrontLeft->value);

        $install = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");
        $installationId = $install->json('data.active_installation.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-10',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 200,
                'stint_km' => 1000,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-11',
                'stint_km' => 1500,
            ])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-12',
                'km_at_installation' => 15000,
            ])
            ->assertCreated();

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $response->assertOk();
        $timeline = $response->json('timeline');
        $this->assertNotEmpty($timeline);

        for ($index = 0; $index < count($timeline) - 1; $index++) {
            $this->assertGreaterThanOrEqual(
                $timeline[$index + 1]['sort_at'],
                $timeline[$index]['sort_at'],
            );
        }

        $chronological = array_reverse($timeline);
        $installCount = 0;
        $seenRemoveBetweenInstalls = true;

        foreach ($chronological as $event) {
            if ($event['type'] === 'installed') {
                if ($installCount > 0 && ! $seenRemoveBetweenInstalls) {
                    $this->fail('Second install appeared before a remove event.');
                }
                $installCount++;
                $seenRemoveBetweenInstalls = false;
            }

            if ($event['type'] === 'removed') {
                $seenRemoveBetweenInstalls = true;
            }
        }

        $this->assertGreaterThanOrEqual(2, $installCount);
    }

    public function test_rotate_swaps_tyres_when_target_position_is_occupied(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $leftTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-LEFT', TyrePosition::FrontLeft->value);
        $rightTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-RIGHT', TyrePosition::FrontRight->value);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$leftTyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 500,
                'stint_km' => 8000,
                'reason' => 'Steer swap',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.new_position', TyrePosition::FrontRight->value);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $leftTyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => TyrePosition::FrontRight->value,
            'removed_at' => null,
        ]);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $rightTyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => TyrePosition::FrontLeft->value,
            'removed_at' => null,
        ]);

        $this->assertDatabaseHas('tyre_rotations', [
            'tyre_id' => $leftTyreId,
            'previous_position' => TyrePosition::FrontLeft->value,
            'new_position' => TyrePosition::FrontRight->value,
            'cost' => 500,
        ]);

        $this->assertDatabaseHas('tyre_expenses', [
            'tyre_id' => $leftTyreId,
            'expense_type' => 'ROTATION',
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('tyre_rotations', [
            'tyre_id' => $rightTyreId,
            'previous_position' => TyrePosition::FrontRight->value,
            'new_position' => TyrePosition::FrontLeft->value,
        ]);
    }

    public function test_early_removal_shows_recommendation_when_life_remaining(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-EARLY',
                'expected_life_km' => 40000,
                'purchase_cost' => 20000,
            ]);

        $tyreId = $create->json('data.id');

        $install = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
                'km_at_installation' => 1000,
            ]);

        $installationId = $install->json('data.id');

        $preview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyre-installations/{$installationId}/removal-preview?stint_km=1000");

        $preview->assertOk();
        $preview->assertJsonPath('data.level', 'warning');
        $this->assertStringContainsString('1000', $preview->json('data.message'));
    }

    public function test_odometer_auto_calculates_stint_on_install_remove_and_transfer(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicleB = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TY9998',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 10000,
        ]);

        $vehicleA->update(['odometer_km' => 5000]);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-ODO',
                'expected_life_km' => 40000,
                'current_km' => 0,
            ]);

        $tyreId = $create->json('data.id');

        $install = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleA->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ]);

        $installationId = $install->json('data.id');
        $install->assertJsonPath('data.km_at_installation', 5000);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/tyre-installations/{$installationId}/stint-km", [
                'odometer_km' => 6000,
            ])
            ->assertOk()
            ->assertJsonPath('data.stint_km', 1000);

        $show = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.active_stint_km', 1000);
        $show->assertJsonPath('life_summary.life_used_percent', 2.5);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-01',
                'odometer_km' => 6000,
            ])
            ->assertOk()
            ->assertJsonPath('data.running_km', 1000)
            ->assertJsonPath('data.km_at_removal', 6000);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-02',
                'km_at_installation' => 10000,
            ])
            ->assertCreated();

        $afterTransfer = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $afterTransfer->assertJsonPath('life_summary.stock_km', 1000);
        $afterTransfer->assertJsonPath('life_summary.life_used_percent', 2.5);

        $newInstallationId = $afterTransfer->json('data.active_installation.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/tyre-installations/{$newInstallationId}/stint-km", [
                'odometer_km' => 12000,
            ])
            ->assertOk()
            ->assertJsonPath('data.stint_km', 2000);

        $final = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $final->assertJsonPath('life_summary.total_km_used', 3000);
        $final->assertJsonPath('life_summary.remaining_life_km', 37000);
        $final->assertJsonPath('life_summary.life_used_percent', 7.5);
    }

    public function test_manual_stint_km_updates_life_without_vehicle_odometer(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-MANUAL',
                'expected_life_km' => 40000,
                'current_km' => 0,
            ]);

        $tyreId = $create->json('data.id');

        $install = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ]);

        $installationId = $install->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/tyre-installations/{$installationId}/stint-km", [
                'stint_km' => 8000,
            ])
            ->assertOk();

        $show = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertOk();
        $show->assertJsonPath('life_summary.life_used_percent', 20);
        $show->assertJsonPath('life_summary.active_stint_km', 8000);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-01',
                'stint_km' => 12000,
            ])
            ->assertOk();

        $afterRemove = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $afterRemove->assertJsonPath('life_summary.total_km_used', 12000);
        $afterRemove->assertJsonPath('life_summary.life_used_percent', 30);
    }

    public function test_tyre_index_can_filter_by_truck(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $onTruckId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-ON-TRUCK', TyrePosition::FrontLeft->value);

        $otherVehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TY9999',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $this->createAndInstallTyre($tenant, $otherVehicle, 'TYRE-OTHER-TRUCK', TyrePosition::FrontLeft->value);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-STOCK',
            'purchase_cost' => 10000,
            'status' => TyreStatus::InStock,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres?vehicle_id={$vehicle->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $onTruckId);
        $response->assertJsonPath('data.0.serial_number', 'TYRE-ON-TRUCK');
    }

    public function test_admin_can_correct_km_already_used_on_edit(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-FIX-KM',
                'expected_life_km' => 40000,
                'current_km' => 10000,
            ]);

        $tyreId = $create->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/tyres/{$tyreId}", [
                'current_km' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_km', 0);

        $show = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.stock_km', 0);
        $show->assertJsonPath('life_summary.life_used_percent', 0);
    }

    public function test_used_tyre_shows_prior_life_at_install(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-OLD',
                'expected_life_km' => 40000,
                'current_km' => 18000,
            ]);

        $tyreId = $create->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ])
            ->assertCreated();

        $show = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.stock_km', 18000);
        $show->assertJsonPath('life_summary.life_used_percent', 45);
    }

    public function test_moderator_can_manage_tyres_without_delete_permission(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('moderator'), 0, 2);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/tyres')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-MOD',
            ])
            ->assertOk();
    }

    private function createAndInstallTyre(Tenant $tenant, Vehicle $vehicle, string $serial, string $positionCode): string
    {
        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => $serial,
                'purchase_cost' => 20000,
            ]);

        $create->assertOk();
        $tyreId = $create->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => $positionCode,
                'installed_at' => '2026-03-01',
                'km_at_installation' => 1000,
            ])
            ->assertCreated();

        return $tyreId;
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tyre Tenant',
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

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TY0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 5000,
        ]);

        TyreLayoutDefaults::seedForTenant($tenant->id);

        return [$tenant, $user, $vehicle];
    }
}
