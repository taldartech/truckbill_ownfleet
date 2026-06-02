<?php

namespace Tests\Feature\Api;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_fleet_dashboard_stats_return_live_counts_for_admin(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Dash Driver', 800);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'PRESENT',
            'wage_amount' => 800,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.vehicles_count', 1)
            ->assertJsonPath('fleet.drivers_count', 1)
            ->assertJsonPath('fleet.present_today_count', 1)
            ->assertJsonPath('fleet.absent_today_count', 0);
    }

    public function test_moderator_can_view_reports_but_not_export(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');

        $this->actingAs($user)
            ->getJson('/app/api/reports/attendance')
            ->assertOk();

        $this->actingAs($user)
            ->get('/app/api/reports/attendance/export')
            ->assertForbidden();
    }

    public function test_accountant_can_export_attendance_report_csv(): void
    {
        [$tenant, $user] = $this->createTenantUser('accountant');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Export Driver', 700);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-05-01',
            'status' => 'PRESENT',
            'wage_amount' => 700,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $response = $this->actingAs($user)
            ->get('/app/api/reports/attendance/export?from_date=2026-05-01&to_date=2026-05-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export Driver', $response->streamedContent());
    }

    public function test_driver_assignments_can_be_filtered_by_vehicle_on_app_api(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicleA = $this->createVehicle($tenant, 'MH01AB1234');
        $vehicleB = $this->createVehicle($tenant, 'MH02CD5678');
        $driver = $this->createDriver($tenant, 'Assigned Driver', 600);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subMonth()->toDateString(),
            'is_primary' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subMonth()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/app/api/driver-assignments?vehicle_id={$vehicleA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_vehicle_list_includes_current_primary_driver(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary On Truck', 650);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicles')
            ->assertOk()
            ->assertJsonPath('data.0.current_primary_assignment.driver.name', 'Primary On Truck');
    }

    public function test_moderator_cannot_create_driver_assignment(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Target Driver', 500);

        $this->actingAs($user)
            ->postJson('/app/api/driver-assignments', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'assigned_from' => now()->toDateString(),
                'is_primary' => true,
            ])
            ->assertForbidden();
    }

    public function test_expiring_documents_count_appears_for_roles_with_document_view(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.expiring_documents_count', 1);
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

    private function createVehicle(Tenant $tenant, ?string $registration = null): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => $registration ?? strtoupper(fake()->bothify('??##??####')),
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
        ]);
    }

    private function createDriver(Tenant $tenant, string $name, float $dailyWage): Driver
    {
        return Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => fake()->numerify('9#########'),
            'license_number' => strtoupper(fake()->bothify('DL-#####')),
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => $dailyWage,
            'is_active' => true,
        ]);
    }
}
