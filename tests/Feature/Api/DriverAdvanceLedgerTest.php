<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\DriverAdvanceEntry;
use App\DriverAssignment;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverAdvanceLedgerTest extends TestCase
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

    public function test_dr_and_cr_update_wallet_balance(): void
    {
        [$tenant, $user, $driver] = $this->seedDriverContext();

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/drivers/{$driver->id}/advance-entries", [
                'entry_type' => 'dr',
                'amount' => 10000,
                'entry_date' => '2026-06-01',
                'remarks' => 'Cash advance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance', 10000);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/drivers/{$driver->id}/advance-entries", [
                'entry_type' => 'cr',
                'amount' => 2000,
                'entry_date' => '2026-06-10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance', 8000);

        $this->assertSame(8000.0, DriverAdvanceEntry::balanceFor($driver));

        $ledger = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/drivers/{$driver->id}/advance-ledger");

        $ledger->assertOk();
        $ledger->assertJsonPath('data.balance', 8000);
        $ledger->assertJsonCount(2, 'data.entries');
    }

    public function test_close_month_does_not_deduct_wallet_from_net_salary(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/drivers/{$driver->id}/advance-entries", [
                'entry_type' => 'dr',
                'amount' => 5000,
                'entry_date' => '2026-06-05',
            ])
            ->assertCreated();

        $close = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06']);

        $close->assertOk();
        $close->assertJsonPath('data.0.advance_deducted', '0.00');
        $close->assertJsonPath('data.0.net_salary', '30000.00');

        $this->assertSame(5000.0, DriverAdvanceEntry::balanceFor($driver));
    }

    public function test_settlement_sheet_includes_advance_fields(): void
    {
        [$tenant, $user] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $sheet->assertOk();
        $line = $sheet->json('data.payroll_lines.0');

        $this->assertIsArray($line);
        $this->assertArrayHasKey('advance_deducted', $line);
        $this->assertArrayHasKey('diesel_deduction', $line);
        $this->assertArrayHasKey('bonus', $line);
        $this->assertArrayHasKey('total_advance', $line);
    }

    public function test_settlement_patch_can_set_advance_deducted(): void
    {
        [$tenant, $user, , $driver] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $wageId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06')
            ->json('data.payroll_lines.0.monthly_wage_id');

        $patch = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/monthly-driver-wages/{$wageId}/settlement", [
                'advance_deducted' => 9999,
                'total_advance' => 8888,
                'extra_deductions' => 0,
            ]);

        $patch->assertOk();
        $patch->assertJsonPath('data.advance_deducted', '9999.00');

        $this->assertDatabaseHas('monthly_driver_wages', [
            'id' => $wageId,
            'driver_id' => $driver->id,
            'advance_deducted' => 9999,
        ]);
    }

    public function test_settlement_patch_bonus_increases_net_salary(): void
    {
        [$tenant, $user, , $driver] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $line = $sheet->json('data.payroll_lines.0');
        $wageId = $line['monthly_wage_id'];
        $gross = (float) $line['gross_earning'];

        $patch = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/monthly-driver-wages/{$wageId}/settlement", [
                'bonus' => 1500,
                'extra_deductions' => 0,
                'advance_deducted' => 0,
                'diesel_deduction' => 0,
            ]);

        $patch->assertOk();
        $patch->assertJsonPath('data.bonus', '1500.00');
        $patch->assertJsonPath('data.net_salary', number_format($gross + 1500, 2, '.', ''));

        $this->assertDatabaseHas('monthly_driver_wages', [
            'id' => $wageId,
            'driver_id' => $driver->id,
            'bonus' => 1500,
        ]);
    }

    public function test_settlement_patch_diesel_deduction_reduces_net_salary(): void
    {
        [$tenant, $user, , $driver] = $this->seedAssignedDriver(30000.00);

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/attendances/close-month', ['month' => '2026-06'])
            ->assertOk();

        $sheet = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/attendances/settlement-sheet?month=2026-06');

        $line = $sheet->json('data.payroll_lines.0');
        $wageId = $line['monthly_wage_id'];
        $gross = (float) $line['gross_earning'];

        $patch = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->patchJson("/api/monthly-driver-wages/{$wageId}/settlement", [
                'diesel_deduction' => 2500,
                'extra_deductions' => 0,
                'advance_deducted' => 0,
            ]);

        $patch->assertOk();
        $patch->assertJsonPath('data.diesel_deduction', '2500.00');
        $patch->assertJsonPath('data.net_salary', number_format($gross - 2500, 2, '.', ''));

        $this->assertDatabaseHas('monthly_driver_wages', [
            'id' => $wageId,
            'driver_id' => $driver->id,
            'diesel_deduction' => 2500,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Driver}
     */
    private function seedDriverContext(): array
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

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wallet Driver',
            'phone' => fake()->numerify('9#########'),
            'license_number' => 'DL-WALLET',
            'daily_wage' => 10000,
            'is_active' => true,
        ]);

        return [$tenant, $user, $driver];
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver}
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

        $driver = Driver::query()->create([
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
