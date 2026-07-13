<?php

namespace Tests\Unit;

use App\Branch;
use App\Enums\TyreExpenseType;
use App\Modules\TyreManagement\Services\TyreReportService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\TyreLifeCalculator;
use App\Tenant;
use App\Tyre;
use App\TyreExpense;
use App\TyreInstallation;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TyreReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private TyreReportService $service;

    private Tenant $tenant;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'short_name' => 'TT',
            'timezone' => 'UTC',
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->tenant->id,
            'branch_name' => 'Main Branch',
            'branch_contact_number' => '9999999999',
            'head_office' => true,
        ]);

        TenantContext::setTenantId($this->tenant->id);
        BranchContext::setBranchId((int) $this->branch->id);

        $this->service = new TyreReportService(new TyreLifeCalculator());
    }

    public function test_cost_per_km_can_be_filtered_by_vehicle(): void
    {
        $vehicleA = $this->createVehicle('KA01A0001');
        $vehicleB = $this->createVehicle('KA01B0001');

        $tyreA = $this->createTyre('TYRE-A');
        $tyreB = $this->createTyre('TYRE-B');

        $this->createInstallation($tyreA, $vehicleA);
        $this->createInstallation($tyreB, $vehicleB);

        $this->createExpense($tyreA, 120.50);
        $this->createExpense($tyreB, 220.75);

        $rows = $this->service->costPerKm([
            'from' => now()->subMonths(2)->toDateString(),
            'to' => now()->toDateString(),
            'vehicle_id' => $vehicleA->id,
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('TYRE-A', $rows[0]['serial_number']);
    }

    public function test_expense_summary_can_be_filtered_by_vehicle(): void
    {
        $vehicleA = $this->createVehicle('KA01C0001');
        $vehicleB = $this->createVehicle('KA01D0001');

        $tyreA = $this->createTyre('TYRE-C');
        $tyreB = $this->createTyre('TYRE-D');

        $this->createInstallation($tyreA, $vehicleA);
        $this->createInstallation($tyreB, $vehicleB);

        $this->createExpense($tyreA, 100.00);
        $this->createExpense($tyreB, 300.00);

        $summary = $this->service->expenseSummary([
            'from' => now()->subMonths(2)->toDateString(),
            'to' => now()->toDateString(),
            'vehicle_id' => $vehicleA->id,
        ]);

        $this->assertSame(100.0, $summary[TyreExpenseType::Repair->value]);
    }

    private function createVehicle(string $registrationNumber): Vehicle
    {
        return Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'registration_number' => $registrationNumber,
            'vehicle_type' => 'TRUCK',
            'tyre_layout' => '4x2',
            'is_active' => true,
        ]);
    }

    private function createTyre(string $serialNumber): Tyre
    {
        return Tyre::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'serial_number' => $serialNumber,
            'brand' => 'Apollo',
            'size' => '295/80R22.5',
            'purchase_date' => now()->subDays(10)->toDateString(),
            'purchase_cost' => 1000.00,
            'current_km' => 0,
            'expected_life_km' => 40000,
            'status' => 'IN_STOCK',
        ]);
    }

    private function createInstallation(Tyre $tyre, Vehicle $vehicle): TyreInstallation
    {
        return TyreInstallation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'tyre_id' => $tyre->id,
            'vehicle_id' => $vehicle->id,
            'position_code' => 'DR',
            'installed_at' => now()->subMonths(4)->toDateString(),
            'running_km' => 1000.00,
        ]);
    }

    private function createExpense(Tyre $tyre, float $amount): TyreExpense
    {
        return TyreExpense::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'tyre_id' => $tyre->id,
            'expense_type' => TyreExpenseType::Repair,
            'amount' => $amount,
            'expense_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
