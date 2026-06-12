<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\DriverAssignment;
use App\Enums\PayrollPayDaysMode;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollPayDaysSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        Carbon::setTestNow('2026-01-31 12:00:00', 'Asia/Kolkata');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payroll_setting_defaults_to_calendar_days(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/payroll-settings')
            ->assertOk()
            ->assertJsonPath('settings.payroll_pay_days_mode', PayrollPayDaysMode::Calendar->value);
    }

    public function test_admin_can_enable_fixed_thirty_day_payroll_setting(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/api/payroll-settings', [
                'payroll_pay_days_mode' => PayrollPayDaysMode::Fixed30->value,
            ])
            ->assertOk()
            ->assertJsonPath('settings.payroll_pay_days_mode', PayrollPayDaysMode::Fixed30->value);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'payroll_pay_days_mode' => PayrollPayDaysMode::Fixed30->value,
        ]);
    }

    public function test_settlement_sheet_uses_fixed_thirty_day_divisor_when_enabled(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(30000.00);
        $tenant->payroll_pay_days_mode = PayrollPayDaysMode::Fixed30;
        $tenant->save();

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances', [
                'vehicle_id' => $vehicle->id,
                'primary_driver_id' => $driver->id,
                'attendance_date' => '2026-01-10',
                'status' => 'ABSENT',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-01'])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-01')
            ->assertOk()
            ->assertJsonPath('data.pay_days', 30)
            ->assertJsonPath('data.payroll_pay_days_mode', PayrollPayDaysMode::Fixed30->value)
            ->assertJsonPath('data.payroll_lines.0.pay_days', 30)
            ->assertJsonPath('data.payroll_lines.0.present_days', 30)
            ->assertJsonPath('data.payroll_lines.0.absent_days', 1)
            ->assertJsonPath('data.payroll_lines.0.gross_earning', 29000)
            ->assertJsonPath('data.payroll_lines.0.absent_deduction', 1000);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver}
     */
    private function seedAssignedDriver(float $dailyWage): array
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Payroll Driver', $dailyWage);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Payroll Test Co',
            'slug' => 'payroll-test-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Payroll Admin',
            'email' => 'payroll-admin@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user];
    }

    private function createVehicle(Tenant $tenant): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12PAY1234',
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
            'phone' => '9000000001',
            'license_number' => 'DL-PAY-001',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => $dailyWage,
            'is_active' => true,
        ]);
    }
}
