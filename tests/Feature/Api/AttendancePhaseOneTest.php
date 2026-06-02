<?php

namespace Tests\Feature\Api;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_attendance_auto_sets_wage_for_present_driver(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary Driver', 950.00);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'PRESENT',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.wage_owner_driver_id', $driver->id);
        $response->assertJsonPath('data.wage_amount', '950.00');
        $response->assertJsonPath('data.wage_payment_status', 'unpaid');
        $response->assertJsonPath('data.marked_via', 'admin_panel');
    }

    public function test_absent_with_alternate_driver_keeps_wage_on_primary(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $primary = $this->createDriver($tenant, 'Primary Driver', 800.00);
        $alternate = $this->createDriver($tenant, 'Alternate Driver', 700.00);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $primary->id,
                'temporary_driver_id' => $alternate->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'ABSENT',
                'temporary_assignment_remarks' => 'Primary on leave',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.wage_owner_driver_id', $primary->id);
        $response->assertJsonPath('data.wage_amount', '800.00');
        $response->assertJsonPath('data.temporary_driver.id', $alternate->id);
    }

    public function test_cannot_mark_wage_paid_when_no_wage_is_due(): void
    {
        [$tenant, $user] = $this->createTenantUser('ACCOUNTANT');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Driver', 500.00);

        $attendance = Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'ABSENT',
            'wage_amount' => 0,
            'wage_payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/attendances/{$attendance->id}/mark-wage-paid")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['wage_amount']);
    }

    public function test_accountant_can_mark_wage_paid_but_not_create_attendance(): void
    {
        [$tenant, $user] = $this->createTenantUser('ACCOUNTANT');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Driver', 500.00);

        $attendance = Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'PRESENT',
            'wage_amount' => 500,
            'wage_payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => now()->addDay()->toDateString(),
                'status' => 'PRESENT',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/attendances/{$attendance->id}/mark-wage-paid", [
                'wage_transfer_notes' => 'Paid in cash to primary driver',
            ])
            ->assertOk()
            ->assertJsonPath('data.wage_payment_status', 'paid');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'wage_payment_status' => 'paid',
        ]);
    }

    public function test_driver_master_stores_extended_fields(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', [
                'name' => 'Extended Driver',
                'phone' => '9876543210',
                'address' => 'Main Road, Pune',
                'aadhaar_number' => '123456789012',
                'pan_number' => 'ABCDE1234F',
                'joining_date' => '2024-01-15',
                'emergency_contact_name' => 'Ramesh',
                'emergency_contact_phone' => '9123456789',
                'daily_wage' => 1100,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenant->id,
            'name' => 'Extended Driver',
            'address' => 'Main Road, Pune',
            'aadhaar_number' => '123456789012',
            'pan_number' => 'ABCDE1234F',
        ]);
    }

    public function test_primary_assignment_closes_previous_open_assignment(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $driverA = $this->createDriver($tenant, 'Driver A', 600);
        $driverB = $this->createDriver($tenant, 'Driver B', 650);

        Sanctum::actingAs($user);

        $first = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subMonths(2)->toDateString(),
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driverB->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->toDateString(),
                'is_primary' => true,
            ])
            ->assertCreated();

        $first->refresh();

        $this->assertNotNull($first->assigned_to);
    }

    public function test_month_overview_returns_grouped_lightweight_payload(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Overview Driver', 900.00);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-03-10',
            'status' => 'PRESENT',
            'wage_amount' => 900,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date=2026-03-01&to_date=2026-03-31');

        $response->assertOk();
        $response->assertJsonPath('data.summary.marked', 1);
        $response->assertJsonPath('data.summary.present', 1);
        $response->assertJsonPath('data.rows.0.vehicle_label', $vehicle->registration_number);
        $response->assertJsonPath('data.rows.0.days.2026-03-10.status', 'PRESENT');
        $response->assertJsonMissingPath('data.rows.0.days.2026-03-10.vehicle');
    }

    public function test_month_overview_is_available_on_app_api_prefix(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'App Api Driver', 500.00);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-05-15',
            'status' => 'PRESENT',
            'wage_amount' => 500,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/attendances/month-overview?from_date=2026-05-01&to_date=2026-05-31')
            ->assertOk()
            ->assertJsonPath('data.summary.marked', 1);
    }

    public function test_attendance_index_supports_pagination_and_filters(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicleA = $this->createVehicle($tenant);
        $vehicleB = $this->createVehicle($tenant);
        $driverA = $this->createDriver($tenant, 'Driver A', 700.00);
        $driverB = $this->createDriver($tenant, 'Driver B', 800.00);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleA->id,
            'primary_driver_id' => $driverA->id,
            'wage_owner_driver_id' => $driverA->id,
            'attendance_date' => '2026-04-01',
            'status' => 'PRESENT',
            'wage_amount' => 700,
            'wage_payment_status' => 'paid',
            'marked_via' => 'admin_panel',
        ]);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleB->id,
            'primary_driver_id' => $driverB->id,
            'wage_owner_driver_id' => $driverB->id,
            'attendance_date' => '2026-04-02',
            'status' => 'ABSENT',
            'wage_amount' => 0,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances?from_date=2026-04-01&to_date=2026-04-30&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(1, 'data');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances?from_date=2026-04-01&to_date=2026-04-30&vehicle_id='.$vehicleA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle.id', $vehicleA->id);
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
