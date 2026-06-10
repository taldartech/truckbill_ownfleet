<?php

namespace Tests\Feature\Api;

use App\AttendanceMonthLock;
use App\DriverAssignment;
use App\Enums\AttendanceMonthStatus;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceExceptionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        Carbon::setTestNow('2026-06-30 12:00:00', 'Asia/Kolkata');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_month_overview_includes_implicit_present_from_primary_assignment(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(10000.00);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date=2026-06-01&to_date=2026-06-30');

        $response->assertOk();
        $response->assertJsonPath('data.summary.present', 30);
        $response->assertJsonPath('data.summary.exceptions', 0);
        $response->assertJsonPath('data.rows.0.days.2026-06-01.status', 'PRESENT');
        $response->assertJsonPath('data.rows.0.days.2026-06-01.is_implicit', true);
    }

    public function test_absent_exception_reduces_auto_present_and_close_month_uses_present_days_pay(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => '2026-06-10',
                'status' => 'ABSENT',
            ])
            ->assertCreated();

        $overview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date=2026-06-01&to_date=2026-06-30');

        $overview->assertJsonPath('data.summary.present', 29);
        $overview->assertJsonPath('data.summary.exceptions', 1);

        $close = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06']);

        $close->assertOk();
        $close->assertJsonPath('data.0.payable_days', 29);
        $close->assertJsonPath('data.0.total_wage', '29000.00');

        $this->assertDatabaseHas('attendance_month_locks', [
            'tenant_id' => $tenant->id,
            'status' => AttendanceMonthStatus::Closed->value,
        ]);
    }

    public function test_future_month_days_are_not_auto_present(): void
    {
        Carbon::setTestNow('2026-06-03 12:00:00', 'Asia/Kolkata');

        [$tenant, $user] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date=2026-07-01&to_date=2026-07-31');

        $response->assertOk();
        $response->assertJsonPath('data.summary.present', 0);
        $response->assertJsonPath('data.rows.0.days.2026-07-01.status', null);
        $response->assertJsonPath('data.rows.0.days.2026-07-01.is_future', true);

        $june = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/month-overview?from_date=2026-06-01&to_date=2026-06-30');

        $june->assertJsonPath('data.summary.present', 3);
        $june->assertJsonPath('data.rows.0.days.2026-06-03.is_implicit', true);
        $june->assertJsonPath('data.rows.0.days.2026-06-04.is_future', true);
    }

    public function test_present_status_cannot_be_posted_manually(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(5000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => '2026-06-05',
                'status' => 'PRESENT',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_exceptions_are_blocked_after_month_is_closed(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(9000.00);

        AttendanceMonthLock::query()->create([
            'tenant_id' => $tenant->id,
            'month' => '2026-06-01',
            'status' => AttendanceMonthStatus::Closed,
            'closed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => '2026-06-12',
                'status' => 'ABSENT',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendance_date']);
    }

    public function test_settlement_sheet_vehicle_matrix_shows_day_marks(): void
    {
        [$tenant, $user] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $sheet->assertOk();
        $sheet->assertJsonPath('data.vehicle_matrix.0.days.1', 'P');
        $sheet->assertJsonPath('data.vehicle_matrix.0.days.30', 'P');
        $sheet->assertJsonPath('data.vehicle_matrix.0.salary', 30000);
    }

    public function test_settlement_sheet_can_be_exported_as_excel(): void
    {
        [$tenant, $user] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/app/api/attendances/settlement-sheet/export?month=2026-06');

        $response->assertOk();
        $response->assertDownload('monthly-settlement-2026-06.xlsx');
    }

    public function test_substitute_only_line_has_zero_payroll_gross(): void
    {
        [$tenant, $user, $vehicleOne, $primaryDriver] = $this->seedAssignedDriver(30000.00);

        $vehicleTwo = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB8888',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);

        $assignedDriver = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Truck Two Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-TWO',
            'daily_wage' => 30000.00,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleTwo->id,
            'driver_id' => $assignedDriver->id,
            'assigned_from' => '2026-06-01',
            'is_primary' => true,
        ]);

        $freeSubstitute = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bench Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-BENCH',
            'daily_wage' => 30000.00,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicleTwo->id,
                'primary_driver_id' => $assignedDriver->id,
                'attendance_date' => '2026-06-05',
                'status' => 'ABSENT',
                'temporary_driver_id' => $freeSubstitute->id,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $sheet->assertOk();

        $substituteLine = collect($sheet->json('data.payroll_lines'))
            ->firstWhere('driver_id', $freeSubstitute->id);

        $this->assertNotNull($substituteLine);
        $this->assertSame(1, $substituteLine['substitute_days']);
        $this->assertSame('1000.00', number_format((float) $substituteLine['substitute_pay'], 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $substituteLine['gross_earning'], 2, '.', ''));
    }

    public function test_cannot_assign_primary_driver_from_another_truck_as_substitute(): void
    {
        [$tenant, $user, $vehicleOne, $primaryDriver] = $this->seedAssignedDriver(30000.00);

        $vehicleTwo = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB8888',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);

        $primaryTwo = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Truck Two Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-TWO',
            'daily_wage' => 30000.00,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleTwo->id,
            'driver_id' => $primaryTwo->id,
            'assigned_from' => '2026-06-01',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicleTwo->id,
                'primary_driver_id' => $primaryTwo->id,
                'attendance_date' => '2026-06-05',
                'status' => 'ABSENT',
                'temporary_driver_id' => $primaryDriver->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['temporary_driver_id']);
    }

    public function test_cannot_assign_driver_already_substituting_on_another_truck(): void
    {
        [$tenant, $user, $vehicleOne, $primaryOne] = $this->seedAssignedDriver(30000.00);

        $vehicleTwo = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB7777',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);

        $primaryTwo = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Primary Two',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-TWO',
            'daily_wage' => 30000.00,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleTwo->id,
            'driver_id' => $primaryTwo->id,
            'assigned_from' => '2026-06-01',
            'is_primary' => true,
        ]);

        $freeSubstitute = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bench Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-BENCH',
            'daily_wage' => 25000.00,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicleOne->id,
                'primary_driver_id' => $primaryOne->id,
                'attendance_date' => '2026-06-08',
                'status' => 'ABSENT',
                'temporary_driver_id' => $freeSubstitute->id,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicleTwo->id,
                'primary_driver_id' => $primaryTwo->id,
                'attendance_date' => '2026-06-08',
                'status' => 'ABSENT',
                'temporary_driver_id' => $freeSubstitute->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['temporary_driver_id']);
    }

    public function test_all_substitute_marked_days_count_even_when_wage_owner_missing(): void
    {
        [$tenant, $user, $vehicleOne] = $this->seedAssignedDriver(50000.00);

        $substitute = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bench Substitute',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-BENCH',
            'daily_wage' => 50000.00,
            'is_active' => true,
        ]);

        $vehicleTwo = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB7777',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);

        $primaryTwo = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Primary Two',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-TWO',
            'daily_wage' => 30000.00,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleTwo->id,
            'driver_id' => $primaryTwo->id,
            'assigned_from' => '2026-06-01',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);

        foreach (['2026-06-03', '2026-06-12'] as $date) {
            $this->withHeader('X-Tenant-Id', $tenant->id)
                ->postJson('/api/attendances', [
                    'vehicle_id' => $vehicleTwo->id,
                    'primary_driver_id' => $primaryTwo->id,
                    'attendance_date' => $date,
                    'status' => 'ABSENT',
                    'temporary_driver_id' => $substitute->id,
                ])
                ->assertCreated();
        }

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $line = collect($sheet->json('data.payroll_lines'))
            ->firstWhere('driver_id', $substitute->id);

        $this->assertNotNull($line);
        $this->assertSame(2, $line['substitute_days']);
        $this->assertSame('3333.34', number_format((float) $line['substitute_pay'], 2, '.', ''));
    }

    public function test_mark_all_paid_reopen_and_unlock_month(): void
    {
        [$tenant, $user] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/mark-all-monthly-paid', ['month' => '2026-06'])
            ->assertOk()
            ->assertJsonPath('data.0.payment_status', 'paid');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/lock-month', ['month' => '2026-06'])
            ->assertOk()
            ->assertJsonPath('data.status', AttendanceMonthStatus::Paid->value);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/unlock-month', ['month' => '2026-06'])
            ->assertOk()
            ->assertJsonPath('data.status', AttendanceMonthStatus::Closed->value);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/reopen-month', ['month' => '2026-06'])
            ->assertOk()
            ->assertJsonPath('data.status', AttendanceMonthStatus::Open->value);

        $this->assertDatabaseHas('attendance_month_locks', [
            'tenant_id' => $tenant->id,
            'status' => AttendanceMonthStatus::Open->value,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: \App\Driver}
     */
    private function seedAssignedDriver(float $monthlyBase): array
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
            'user_role_id' => UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB9999',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);

        $driver = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Assigned Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-TEST',
            'daily_wage' => $monthlyBase,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-06-01',
            'is_primary' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }
}
