<?php

namespace Tests\Feature\Api;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\MonthlyDriverWage;
use App\Support\MonthlyWageAccrual;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary Driver', 950.00);
        $month = now()->subMonth()->startOfMonth();
        $overviewDate = $month->copy()->addDays(14)->toDateString();

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => $month->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date='.$month->toDateString().'&to_date='.$month->copy()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertJsonPath("data.rows.0.days.{$overviewDate}.status", 'PRESENT');
        $response->assertJsonPath("data.rows.0.days.{$overviewDate}.is_implicit", true);
        $response->assertJsonPath(
            "data.rows.0.days.{$overviewDate}.wage_amount",
            MonthlyWageAccrual::perDay(950.00, $overviewDate),
        );
    }

    public function test_attendance_uses_primary_driver_from_active_assignment_when_not_sent(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Assigned Driver', 980.00);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date='.now()->startOfMonth()->toDateString().'&to_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('data.rows.0.driver_name', 'Assigned Driver');
        $response->assertJsonPath('data.rows.0.days.'.now()->toDateString().'.is_implicit', true);
    }

    public function test_absent_with_alternate_driver_credits_primary_and_records_substitute_payment(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $primary = $this->createDriver($tenant, 'Primary Driver', 800.00);
        $alternate = $this->createDriver($tenant, 'Alternate Driver', 700.00);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $primary->id,
                'temporary_driver_id' => $alternate->id,
                'attendance_date' => '2026-06-15',
                'status' => 'ABSENT',
                'temporary_assignment_remarks' => 'Primary on leave',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.wage_owner_driver_id', $primary->id);
        $response->assertJsonPath('data.wage_amount', '26.67');
        $response->assertJsonPath('data.substitute_payment_amount', '23.33');
        $response->assertJsonPath('data.temporary_driver.id', $alternate->id);
    }

    public function test_cannot_mark_wage_paid_when_no_wage_is_due(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_moderator_can_mark_daily_attendance_but_not_bulk_mark_or_wages(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');
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
                'status' => 'ABSENT',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/bulk-mark', [
                'vehicle_id' => $vehicle->id,
                'from_date' => now()->toDateString(),
                'to_date' => now()->addDays(2)->toDateString(),
                'attendance_type' => 'ABSENT',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/attendances/{$attendance->id}/mark-wage-paid", [
                'wage_transfer_notes' => 'Paid in cash to primary driver',
            ])
            ->assertForbidden();
    }

    public function test_accountant_can_view_and_export_settlement_but_not_manage_wages(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');

        $accountant = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Company Accountant',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ACCOUNTANT,
        ]);

        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Payroll Driver', 15000.00);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subMonth()->startOfMonth()->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($admin);

        $month = now()->subMonth()->format('Y-m');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => $month])
            ->assertOk();

        Sanctum::actingAs($accountant);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month='.$month)
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/api/attendances/settlement-sheet/export?month='.$month)
            ->assertOk()
            ->assertDownload('monthly-settlement-'.$month.'.xlsx');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => $month])
            ->assertForbidden();

        $wageId = MonthlyDriverWage::query()
            ->where('tenant_id', $tenant->id)
            ->value('id');

        $this->assertNotNull($wageId);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/monthly-driver-wages/{$wageId}/settlement", [
                'bonus' => 500,
            ])
            ->assertForbidden();
    }

    public function test_accountant_can_view_but_not_manage_attendance(): void
    {
        [$tenant, $user] = $this->createTenantUser('accountant');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Driver', 500.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date='.now()->startOfMonth()->toDateString().'&to_date='.now()->endOfMonth()->toDateString())
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month='.now()->format('Y-m'))
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => now()->toDateString(),
                'status' => 'PRESENT',
            ])
            ->assertForbidden();
    }

    public function test_moderator_cannot_access_monthly_settlement(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month='.now()->format('Y-m'))
            ->assertForbidden();
    }

    public function test_driver_master_stores_extended_fields(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

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
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_driver_cannot_be_primary_on_two_trucks_for_same_period(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicleA = $this->createVehicle($tenant);
        $vehicleB = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Single Shift Driver', 700);

        Sanctum::actingAs($user);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleA->id,
            'assigned_from' => '2026-06-01',
            'assigned_to' => '2026-06-30',
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicleB->id,
                'assigned_from' => '2026-06-15',
                'is_primary' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_backdated_open_assignment_is_blocked_when_driver_has_bounded_assignment_on_another_truck(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicleA = $this->createVehicle($tenant);
        $vehicleB = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'jan 2', 700);

        Sanctum::actingAs($user);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleA->id,
            'assigned_from' => '2026-01-11',
            'assigned_to' => '2026-01-20',
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicleB->id,
                'assigned_from' => '2026-01-01',
                'is_primary' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_bounded_assignment_is_blocked_when_driver_has_open_assignment_on_another_truck(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicleA = $this->createVehicle($tenant);
        $vehicleB = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'jan 2', 700);

        Sanctum::actingAs($user);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicleB->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicleA->id,
                'assigned_from' => '2026-01-11',
                'is_primary' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_truck_replaces_existing_primary_driver_without_overlap_error(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driverA = $this->createDriver($tenant, 'Driver One', 650);
        $driverB = $this->createDriver($tenant, 'Driver Two', 700);

        Sanctum::actingAs($user);

        $first = DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => '2026-06-01',
            'assigned_to' => '2026-06-30',
            'is_primary' => true,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/driver-assignments', [
                'driver_id' => $driverB->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => '2026-06-10',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $driverB->id);

        $first->refresh();

        $this->assertSame('2026-06-09', $first->assigned_to?->toDateString());
    }

    public function test_alternate_driver_is_only_for_that_day_and_primary_continues_next_day(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $primary = $this->createDriver($tenant, 'Primary Driver', 900);
        $alternate = $this->createDriver($tenant, 'Alternate Driver', 800);
        $month = now()->subMonth()->startOfMonth();
        $absenceDate = $month->copy()->addDays(9)->toDateString();
        $followingDate = $month->copy()->addDays(10)->toDateString();

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $primary->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => $month->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'attendance_date' => $absenceDate,
                'status' => 'ABSENT',
                'temporary_driver_id' => $alternate->id,
                'temporary_assignment_remarks' => 'Primary on leave',
            ])
            ->assertCreated()
            ->assertJsonPath('data.primary_driver_id', $primary->id)
            ->assertJsonPath('data.wage_owner_driver_id', $primary->id);

        $overview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date='.$month->toDateString().'&to_date='.$month->copy()->endOfMonth()->toDateString());

        $overview->assertOk();
        $overview->assertJsonPath("data.rows.0.days.{$followingDate}.status", 'PRESENT');
        $overview->assertJsonPath("data.rows.0.days.{$followingDate}.is_implicit", true);
    }

    public function test_month_overview_returns_grouped_lightweight_payload(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_close_month_creates_monthly_wage_summary_from_attendance(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00', 'Asia/Kolkata');

        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Monthly Driver', 1000.00);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-06-01',
            'status' => 'PRESENT',
            'wage_amount' => 33.33,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-06-02',
            'status' => 'PRESENT',
            'wage_amount' => 33.33,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', [
                'month' => '2026-06',
            ])
            ->assertOk()
            ->assertJsonPath('data.0.driver_id', $driver->id)
            ->assertJsonPath('data.0.payable_days', 2)
            ->assertJsonPath('data.0.total_wage', '66.67');

        $this->assertDatabaseHas('monthly_driver_wages', [
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'month' => '2026-06-01 00:00:00',
            'payable_days' => 2,
            'payment_status' => 'unpaid',
        ]);

        Carbon::setTestNow();
    }

    public function test_close_month_credits_primary_for_substitute_days_and_records_substitute_reference(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $primary = $this->createDriver($tenant, 'Primary For Month', 1000);
        $alternate = $this->createDriver($tenant, 'Alternate For Month', 900);
        $month = now()->subMonth()->startOfMonth();
        $absenceDate = $month->copy()->addDays(4)->toDateString();
        $presentDate = $month->copy()->addDays(5)->toDateString();
        $alternateWage = MonthlyWageAccrual::perDay(900.00, $absenceDate);
        $primaryWage = MonthlyWageAccrual::perDay(1000.00, $presentDate);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $primary->id,
            'wage_owner_driver_id' => $alternate->id,
            'temporary_driver_id' => $alternate->id,
            'attendance_date' => $absenceDate,
            'status' => 'ABSENT',
            'wage_amount' => $alternateWage,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $primary->id,
            'wage_owner_driver_id' => $primary->id,
            'attendance_date' => $presentDate,
            'status' => 'PRESENT',
            'wage_amount' => $primaryWage,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', [
                'month' => $month->format('Y-m'),
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('monthly_driver_wages', [
            'tenant_id' => $tenant->id,
            'driver_id' => $primary->id,
            'payable_days' => 2,
            'total_wage' => round($primaryWage + MonthlyWageAccrual::perDay(1000.00, $absenceDate), 2),
        ]);
        $this->assertDatabaseHas('monthly_driver_wages', [
            'tenant_id' => $tenant->id,
            'driver_id' => $alternate->id,
            'total_wage' => 0,
        ]);
    }

    public function test_mark_monthly_wage_paid_updates_monthly_and_daily_status(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Pay Monthly', 900.00);

        $attendance = Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-06-10',
            'status' => 'PRESENT',
            'wage_amount' => 900,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $monthly = MonthlyDriverWage::query()->create([
            'tenant_id' => $tenant->id,
            'driver_id' => $driver->id,
            'month' => '2026-06-01',
            'total_days' => 1,
            'payable_days' => 1,
            'total_wage' => 900,
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/monthly-driver-wages/{$monthly->id}/mark-paid", [
                'payment_notes' => 'June settlement paid',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('monthly_driver_wages', [
            'id' => $monthly->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'wage_payment_status' => 'paid',
            'wage_transfer_notes' => 'June settlement paid',
        ]);
    }

    public function test_attendance_index_supports_pagination_and_filters(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
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

    public function test_month_overview_shows_current_driver_when_truck_has_multiple_assignments(): void
    {
        Carbon::setTestNow('2026-06-25 10:00:00');

        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driverA = $this->createDriver($tenant, 'Driver A', 700.00);
        $driverB = $this->createDriver($tenant, 'Driver B', 800.00);
        $driverC = $this->createDriver($tenant, 'Vikas', 900.00);

        $month = now()->startOfMonth();

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverA->id,
            'assigned_from' => $month->toDateString(),
            'assigned_to' => $month->copy()->addDays(9)->toDateString(),
            'is_primary' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverB->id,
            'assigned_from' => $month->copy()->addDays(10)->toDateString(),
            'assigned_to' => $month->copy()->addDays(19)->toDateString(),
            'is_primary' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverC->id,
            'assigned_from' => $month->copy()->addDays(20)->toDateString(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date='.$month->toDateString().'&to_date='.$month->copy()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('data.rows.0.driver_name', 'Vikas');
        $response->assertJsonPath(
            'data.rows.0.days.'.$month->copy()->addDays(5)->toDateString().'.primary_driver_name',
            'Driver A',
        );
        $response->assertJsonPath(
            'data.rows.0.days.'.$month->copy()->addDays(15)->toDateString().'.primary_driver_name',
            'Driver B',
        );
        $response->assertJsonPath(
            'data.rows.0.days.'.$month->copy()->addDays(25)->toDateString().'.primary_driver_name',
            'Vikas',
        );

        Carbon::setTestNow();
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
