<?php

namespace Tests\Feature\Api;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
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

    public function test_admin_can_create_driver_with_uuid_and_tenant_scope(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_cannot_create_driver_with_duplicate_identity_in_same_tenant(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Jan Driver',
            'phone' => '9983921134',
            'license_number' => 'DL-1420110012345',
            'aadhaar_number' => '232323232323',
            'pan_number' => 'ABCDE1234F',
            'joining_date' => '2026-01-01',
            'daily_wage' => 30000,
            'is_active' => true,
        ];

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', $payload)
            ->assertCreated();

        $duplicate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', [
                ...$payload,
                'name' => 'Jan 2 Driver',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['phone', 'license_number', 'aadhaar_number', 'pan_number']);
    }

    public function test_same_driver_identity_allowed_in_different_tenants(): void
    {
        [$tenantA, $userA] = $this->createTenantUser('admin');
        [$tenantB, $userB] = $this->createTenantUser('admin');
        Sanctum::actingAs($userA);

        $payload = [
            'name' => 'Shared Identity Driver',
            'phone' => '9983921134',
            'license_number' => 'DL-1420110012345',
            'aadhaar_number' => '232323232323',
            'pan_number' => 'ABCDE1234F',
            'joining_date' => '2026-01-01',
            'daily_wage' => 30000,
            'is_active' => true,
        ];

        $this->withHeader('X-Tenant-Id', $tenantA->id)
            ->postJson('/api/drivers', $payload)
            ->assertCreated();

        Sanctum::actingAs($userB);

        $this->withHeader('X-Tenant-Id', $tenantB->id)
            ->postJson('/api/drivers', $payload)
            ->assertCreated();
    }

    public function test_driver_index_supports_pagination_and_search(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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
        [$tenantA, $userA] = $this->createTenantUser('admin');
        [$tenantB] = $this->createTenantUser('admin');

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
        [$tenant, $user] = $this->createTenantUser('admin');
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
        $response->assertJsonPath('data.wage_owner_driver_id', $primary->id);
        $response->assertJsonPath('data.wage_amount', '31.67');

        $this->assertDatabaseHas('attendances', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $primary->id,
            'wage_owner_driver_id' => $primary->id,
        ]);
    }

    public function test_license_expiring_endpoint_returns_drivers_within_days_filter(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_primary_driver_can_be_replaced_on_same_effective_date(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driverA = $this->createDriver($tenant, 'Driver A', 600);
        $driverB = $this->createDriver($tenant, 'Driver B', 650);
        $effectiveDate = '2026-06-06';

        Sanctum::actingAs($user);

        $first = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => $effectiveDate,
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driverB->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => $effectiveDate,
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $driverB->id);

        $first->refresh();

        $this->assertSame($effectiveDate, $first->assigned_to?->toDateString());

        $this->assertTrue(
            DriverAssignment::query()
                ->where('driver_id', $driverB->id)
                ->where('vehicle_id', $vehicle->id)
                ->whereDate('assigned_from', $effectiveDate)
                ->whereNull('assigned_to')
                ->exists(),
        );
    }

    public function test_driver_assignment_cannot_start_before_driver_joining_date(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Joined Later Driver', 600, joiningDate: '2026-07-01');

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => '2026-06-01',
                'is_primary' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_from']);
    }

    public function test_driver_assignment_can_start_on_driver_joining_date(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Joined Today Driver', 600, joiningDate: '2026-07-09');

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => '2026-07-09',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.assigned_from', '2026-07-09');
    }

    public function test_driver_assignment_update_cannot_move_before_driver_joining_date(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Joined Later Driver', 600, joiningDate: '2026-07-01');

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-07-01',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/driver-assignments/{$assignment->id}", [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => '2026-06-15',
                'is_primary' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_from']);
    }

    public function test_vehicle_primary_driver_can_be_removed(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Assigned Driver', 700);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subDays(3)->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/vehicles/{$vehicle->id}/remove-primary-driver")
            ->assertOk()
            ->assertJsonPath('vehicle_id', $vehicle->id);

        $assignment->refresh();

        $this->assertSame(now()->toDateString(), $assignment->assigned_to?->toDateString());
    }

    public function test_primary_assignment_endpoint_returns_active_driver(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Active Driver', 700);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/vehicles/{$vehicle->id}/primary-assignment")
            ->assertOk()
            ->assertJsonPath('has_active_primary', true)
            ->assertJsonPath('assignment.driver.name', 'Active Driver');
    }

    public function test_primary_assignment_endpoint_returns_empty_when_driver_removed(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Removed Driver', 700);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subDays(2)->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/vehicles/{$vehicle->id}/remove-primary-driver")
            ->assertOk()
            ->assertJsonPath('has_active_primary', false);

        $assignment->refresh();
        $this->assertSame(now()->toDateString(), $assignment->assigned_to?->toDateString());

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/vehicles/{$vehicle->id}/primary-assignment")
            ->assertOk()
            ->assertJsonPath('has_active_primary', false)
            ->assertJsonPath('assignment', null);
    }

    public function test_new_primary_assignment_cancels_scheduled_future_assignments_on_same_truck(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $currentDriver = $this->createDriver($tenant, 'Current Driver', 600);
        $newDriver = $this->createDriver($tenant, 'New Driver', 650);

        $scheduled = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $currentDriver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->addMonth()->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $newDriver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->toDateString(),
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $newDriver->id);

        $scheduled->refresh();

        $this->assertSame(
            $scheduled->assigned_from->toDateString(),
            $scheduled->assigned_to?->toDateString(),
        );
    }

    public function test_remove_primary_driver_fails_when_truck_has_no_assignment(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/vehicles/{$vehicle->id}/remove-primary-driver")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_admin_can_delete_driver_assignment_without_paid_attendance(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Mistaken Driver', 600);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-09',
            'assigned_to' => '2026-06-09',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/driver-assignments/{$assignment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('driver_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_admin_cannot_delete_current_driver_assignment(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Current Driver', 600);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-01',
            'assigned_to' => null,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/driver-assignments/{$assignment->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_assignment']);

        $this->assertDatabaseHas('driver_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_admin_cannot_delete_driver_assignment_with_linked_trips(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Trip Driver', 600);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-01',
            'assigned_to' => '2026-06-09',
            'is_primary' => true,
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-LINKED-1',
            'status' => 'CREATED',
            'start_at' => '2026-06-03 08:00:00',
            'end_at' => '2026-06-03 18:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/driver-assignments/{$assignment->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_assignment']);

        $this->assertDatabaseHas('driver_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_admin_can_delete_driver_assignment_when_wage_is_paid_in_attendance(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Paid Driver', 600);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-01',
            'assigned_to' => '2026-06-09',
            'is_primary' => true,
        ]);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-06-05',
            'status' => 'PRESENT',
            'wage_amount' => 600,
            'wage_payment_status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/driver-assignments/{$assignment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('driver_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_admin_can_update_driver_assignment_without_paid_attendance(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Wrong Driver', 600);
        $replacement = $this->createDriver($tenant, 'Correct Driver', 650);

        $assignment = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-09',
            'assigned_to' => '2026-06-09',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/driver-assignments/{$assignment->id}", [
                'driver_id' => $replacement->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => '2026-06-09',
                'assigned_to' => '2026-06-09',
                'is_primary' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.driver_id', $replacement->id)
            ->assertJsonPath('data.has_paid_attendance', false);
    }

    public function test_admin_can_soft_delete_driver_and_preserve_linked_trips(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Retired Driver', 700);

        $trip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-SOFT-DEL-1',
            'status' => 'CREATED',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/drivers/{$driver->id}")
            ->assertNoContent();

        $this->assertNull(Driver::query()->find($driver->id));
        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);

        $deletedDriver = Driver::query()->withTrashed()->find($driver->id);
        $this->assertNotNull($deletedDriver);
        $this->assertFalse($deletedDriver->is_active);
        $this->assertSame($user->id, $deletedDriver->deleted_by);

        $this->assertNotNull(Trip::query()->find($trip->id));
        $this->assertSame($driver->id, Trip::query()->find($trip->id)?->driver_id);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/drivers')
            ->assertOk()
            ->assertJsonMissing(['id' => $driver->id]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.driver.name', 'Retired Driver');
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

    public function test_moderator_can_create_driver_but_not_update_or_delete(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');
        $driver = $this->createDriver($tenant, 'Existing Driver', 700);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/drivers', [
                'name' => 'Moderator Driver',
                'phone' => fake()->numerify('9#########'),
                'license_number' => strtoupper(fake()->bothify('DL-#####')),
                'license_expiry_date' => now()->addYear()->toDateString(),
                'joining_date' => '2024-01-15',
                'daily_wage' => 800,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/drivers/{$driver->id}", [
                'name' => 'Should Not Update',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/drivers/{$driver->id}")
            ->assertForbidden();
    }

    public function test_moderator_can_create_driver_assignment_but_not_update_or_delete(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Assignable Driver', 650);

        $assignment = \App\DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $this->createDriver($tenant, 'Second Driver', 600)->id,
                'assigned_from' => now()->addDay()->toDateString(),
                'is_primary' => false,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/driver-assignments/{$assignment->id}", [
                'assigned_to' => now()->addDays(3)->toDateString(),
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/driver-assignments/{$assignment->id}")
            ->assertForbidden();
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

        return [$tenant, $this->assignBranchIfRequired($user, $tenant)];
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

    private function createDriver(
        Tenant $tenant,
        string $name,
        float $dailyWage,
        ?string $expiry = null,
        ?string $joiningDate = null,
    ): Driver {
        return Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => fake()->numerify('9#########'),
            'license_number' => strtoupper(fake()->bothify('DL-#####')),
            'license_expiry_date' => $expiry ?? now()->addYear()->toDateString(),
            'joining_date' => $joiningDate,
            'daily_wage' => $dailyWage,
            'is_active' => true,
        ]);
    }
}
