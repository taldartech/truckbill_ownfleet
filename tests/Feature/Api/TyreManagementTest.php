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
            ->getJson("/api/tyre-installations/{$installationId}/removal-preview?km_at_removal=2000");

        $preview->assertOk();
        $preview->assertJsonPath('data.level', 'warning');
        $this->assertStringContainsString('1000', $preview->json('data.message'));
    }

    public function test_moderator_can_view_but_not_create_tyres_without_manage_permission(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('moderator'), 0, 2);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/tyres')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-DENIED',
            ])
            ->assertForbidden();
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
