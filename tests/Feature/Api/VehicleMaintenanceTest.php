<?php

namespace Tests\Feature\Api;

use App\MaintenanceServiceType;
use App\Support\MaintenanceServiceTypeDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_list_and_update_maintenance_record(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        $oilChangeId = $this->serviceTypeId($tenant, 'OIL_CHANGE');

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-01',
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $oilChangeId,
                'service_provider' => 'City Garage',
                'cost_amount' => 2500,
                'next_service_due_km' => 20000,
                'next_service_due_date' => '2026-12-01',
                'remarks' => 'Synthetic oil used',
            ])
            ->assertCreated()
            ->assertJsonPath('data.service_type', 'OIL_CHANGE')
            ->assertJsonPath('data.service_type_label', 'Oil Change')
            ->assertJsonPath('data.service_provider', 'City Garage');

        $this->assertDatabaseHas('vehicle_maintenances', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'maintenance_service_type_id' => $oilChangeId,
            'service_provider' => 'City Garage',
        ]);

        $vehicle->refresh();
        $this->assertEquals(15000, (float) $vehicle->odometer_km);

        $recordId = VehicleMaintenance::query()->value('id');

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances?search=City')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recordId)
            ->assertJsonPath('data.0.vehicle.registration_number', $vehicle->registration_number);

        $this->actingAs($user)
            ->patchJson("/app/api/vehicle-maintenances/{$recordId}", [
                'remarks' => 'Updated note',
            ])
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Updated note');
    }

    public function test_odometer_sync_never_decreases_vehicle_reading(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        $vehicle->update(['odometer_km' => 20000]);

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-02',
                'odometer_km' => 18000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'GENERAL_SERVICE'),
                'service_provider' => 'City Garage',
                'cost_amount' => 1500,
            ])
            ->assertCreated();

        $vehicle->refresh();
        $this->assertEquals(20000, (float) $vehicle->odometer_km);
    }

    public function test_due_services_uses_latest_record_per_truck_only(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        VehicleMaintenance::query()->create([
            'tenant_id' => $vehicle->tenant_id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-01-01',
            'odometer_km' => 10000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
            'next_service_due_km' => 12000,
            'next_service_due_date' => '2026-02-01',
        ]);

        VehicleMaintenance::query()->create([
            'tenant_id' => $vehicle->tenant_id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-05-01',
            'odometer_km' => 15000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'GENERAL_SERVICE'),
            'next_service_due_km' => 25000,
            'next_service_due_date' => '2027-01-01',
        ]);

        $vehicle->update(['odometer_km' => 13000]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $vehicle->update(['odometer_km' => 26000]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_type', 'GENERAL_SERVICE');
    }

    public function test_vehicle_maintenance_history_endpoint_returns_records(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        VehicleMaintenance::query()->create([
            'tenant_id' => $vehicle->tenant_id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-04-01',
            'odometer_km' => 12000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'BRAKE_REPAIR'),
        ]);

        $this->actingAs($user)
            ->getJson("/app/api/vehicles/{$vehicle->id}/maintenances")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.service_type', 'BRAKE_REPAIR');
    }

    public function test_maintenance_reports_require_reports_permission(): void
    {
        [, $user] = $this->createFixtures('moderator');

        $this->actingAs($user)
            ->getJson('/app/api/reports/maintenance/cost-summary?from=2026-01-01&to=2026-03-31')
            ->assertForbidden();
    }

    public function test_admin_can_view_maintenance_reports(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        VehicleMaintenance::query()->create([
            'tenant_id' => $vehicle->tenant_id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-03-01',
            'odometer_km' => 11000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
            'cost_amount' => 1000,
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/reports/maintenance/cost-summary?from=2026-01-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.0.service_type', 'OIL_CHANGE')
            ->assertJsonPath('data.1.service_type_label', 'Grand Total');
    }

    public function test_maintenance_report_export_returns_excel_for_selected_filters(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        VehicleMaintenance::query()->create([
            'tenant_id' => $vehicle->tenant_id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-03-01',
            'odometer_km' => 11000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
            'cost_amount' => 1000,
        ]);

        $response = $this->actingAs($user)
            ->get('/app/api/reports/maintenance/export?type=cost&from=2026-01-01&to=2026-03-31');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_cannot_create_maintenance_when_next_due_date_is_not_after_service_date(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-01',
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
                'service_provider' => 'City Garage',
                'cost_amount' => 2500,
                'next_service_due_date' => '2026-05-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['next_service_due_date']);
    }

    public function test_cannot_create_maintenance_with_future_service_date(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => now()->addDay()->toDateString(),
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
                'service_provider' => 'City Garage',
                'cost_amount' => 2500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_date']);
    }

    public function test_accountant_cannot_create_maintenance_records(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('accountant');

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-01',
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
                'service_provider' => 'City Garage',
                'cost_amount' => 2500,
            ])
            ->assertForbidden();
    }

    public function test_cannot_create_maintenance_without_service_provider_or_cost_amount(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-01',
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_provider', 'cost_amount']);

        $this->actingAs($user)
            ->postJson('/app/api/vehicle-maintenances', [
                'vehicle_id' => $vehicle->id,
                'service_date' => '2026-06-01',
                'odometer_km' => 15000,
                'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
                'service_provider' => 'City Garage',
                'cost_amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_amount']);
    }

    public function test_maintenance_service_types_are_seeded_and_listed(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->getJson('/app/api/maintenance-service-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'GREASING_AND_LUBRICATION', 'name' => 'Greasing & Lubrication'])
            ->assertJsonFragment(['code' => 'OIL_CHANGE', 'name' => 'Oil Change']);

        $this->assertDatabaseHas('maintenance_service_types', [
            'tenant_id' => $tenant->id,
            'code' => 'GREASING_AND_LUBRICATION',
            'name' => 'Greasing & Lubrication',
        ]);
    }

    public function test_maintenance_list_supports_due_status_filter_and_includes_due_status(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        $vehicle->update(['odometer_km' => 25000]);

        $dueRecord = VehicleMaintenance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-06-01',
            'odometer_km' => 15000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'OIL_CHANGE'),
            'service_provider' => 'City Garage',
            'cost_amount' => 2500,
            'next_service_due_km' => 20000,
            'next_service_due_date' => '2026-12-01',
        ]);

        VehicleMaintenance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $vehicle->branch_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => '2026-05-01',
            'odometer_km' => 12000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'GENERAL_SERVICE'),
            'service_provider' => 'Other Garage',
            'cost_amount' => 1500,
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances?due_status=due')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dueRecord->id)
            ->assertJsonPath('data.0.due_status', 'due')
            ->assertJsonPath('data.0.due_status_label', 'Due');

        $this->actingAs($user)
            ->getJson("/app/api/vehicle-maintenances/{$dueRecord->id}")
            ->assertOk()
            ->assertJsonPath('data.due_status', 'due');
    }

    public function test_due_services_endpoint_supports_filters(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');

        $vehicleKmOnly = Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => 'MH12KM0001',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 40000,
        ]);

        VehicleMaintenance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $vehicleKmOnly->branch_id,
            'vehicle_id' => $vehicleKmOnly->id,
            'service_date' => '2026-06-10',
            'odometer_km' => 10000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'AC_REPAIR'),
            'service_provider' => 'KM Garage',
            'cost_amount' => 1500,
            'next_service_due_km' => 2000,
            'next_service_due_date' => '2026-12-31',
        ]);

        $vehicleBoth = Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => 'MH12BOTH01',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 80000,
        ]);

        VehicleMaintenance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $vehicleBoth->branch_id,
            'vehicle_id' => $vehicleBoth->id,
            'service_date' => '2026-06-01',
            'odometer_km' => 70000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'GENERAL_SERVICE'),
            'service_provider' => 'Both Garage',
            'cost_amount' => 1500,
            'next_service_due_km' => 500,
            'next_service_due_date' => '2026-05-01',
        ]);

        $vehicleDateOnly = Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => 'MH12DATE01',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 10000,
        ]);

        VehicleMaintenance::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $vehicleDateOnly->branch_id,
            'vehicle_id' => $vehicleDateOnly->id,
            'service_date' => '2026-06-01',
            'odometer_km' => 9000,
            'maintenance_service_type_id' => $this->serviceTypeId($tenant, 'GENERAL_SERVICE'),
            'service_provider' => 'Date Garage',
            'cost_amount' => 1500,
            'next_service_due_km' => 50000,
            'next_service_due_date' => '2026-05-01',
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due?overdue_reason=both')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.overdue_reason', 'both')
            ->assertJsonPath('data.0.overdue_reason_label', 'KM & Date');

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due?overdue_reason=date')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['overdue_reason' => 'date'])
            ->assertJsonFragment(['overdue_reason' => 'both']);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due?overdue_reason=km')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['overdue_reason' => 'km'])
            ->assertJsonFragment(['overdue_reason' => 'both']);

        $this->actingAs($user)
            ->getJson('/app/api/vehicle-maintenances/due?vehicle_id='.$vehicleKmOnly->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.overdue_reason', 'km');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Maintenance Tenant',
            'slug' => 'maintenance-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        MaintenanceServiceTypeDefaults::seedForTenant($tenant->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Maintenance User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role),
        ]);

        $user = $this->assignBranchIfRequired($user, $tenant);

        $vehicle = Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => 'MH12AB5678',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 10000,
        ]);

        return [$tenant, $user, $vehicle];
    }

    private function serviceTypeId(Tenant $tenant, string $code): int
    {
        MaintenanceServiceTypeDefaults::seedForTenant($tenant->id);

        return (int) MaintenanceServiceType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->value('id');
    }
}
