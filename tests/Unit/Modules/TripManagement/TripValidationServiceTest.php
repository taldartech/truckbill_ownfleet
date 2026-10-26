<?php

namespace Tests\Unit\Modules\TripManagement;

use App\Driver;
use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Modules\TripManagement\Services\TripValidationService;
use App\Support\BranchProvisioner;
use App\Tenant;
use App\Trip;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TripValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->service = app(TripValidationService::class);
    }

    private function createTenantFleet(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Trip Co',
            'slug' => 'trip-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $branch = BranchProvisioner::ensureDefaultBranch($tenant);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'registration_number' => 'MH12TR0001',
            'vehicle_type' => 'TRUCK',
            'is_active' => true,
        ]);

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Active Primary Driver',
            'phone' => '9888800001',
            'daily_wage' => 700,
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Trip Admin',
            'email' => 'admin@trip.test',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin);
        \App\Support\BranchContext::setBranchId($branch->id);

        return [$tenant, $branch, $vehicle, $driver, $admin];
    }

    public function test_resolve_trip_edit_scope(): void
    {
        [$tenant, $branch, $vehicle, $driver, $admin] = $this->createTenantFleet();

        $trip = new Trip([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::Pending->value,
            'start_at' => now()->toDateString(),
        ]);

        // Regular admin with trips.edit permission on pending trip has full edit scope
        $scope = $this->service->resolveTripEditScope($trip, $admin);
        $this->assertSame(TripValidationService::EDIT_SCOPE_FULL, $scope);

        // Cancelled trip for regular admin has none edit scope
        $trip->status = TripStatus::Cancelled->value;
        $this->assertSame(TripValidationService::EDIT_SCOPE_NONE, $this->service->resolveTripEditScope($trip, $admin));

        // Completed trip for regular admin with trips.expenses has expenses_only scope
        $trip->status = TripStatus::Completed->value;
        $this->assertSame(TripValidationService::EDIT_SCOPE_EXPENSES_ONLY, $this->service->resolveTripEditScope($trip, $admin));
    }

    public function test_assert_trip_is_editable_throws_when_scope_is_none(): void
    {
        [$tenant, $branch, $vehicle, $driver, $admin] = $this->createTenantFleet();

        $trip = new Trip([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::Cancelled->value,
            'start_at' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->assertTripIsEditable($trip, $admin, TripValidationService::EDIT_SCOPE_FULL);
    }

    public function test_assert_driver_assigned_to_vehicle(): void
    {
        [$tenant, $branch, $vehicle, $driver] = $this->createTenantFleet();

        // Start at is null
        try {
            $this->service->assertDriverAssignedToVehicle($vehicle->id, $driver->id, null);
            $this->fail('Expected ValidationException for null start_at');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_at', $e->errors());
        }

        // Driver not assigned
        try {
            $this->service->assertDriverAssignedToVehicle($vehicle->id, $driver->id, '2026-06-01');
            $this->fail('Expected ValidationException for unassigned driver');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('driver_id', $e->errors());
        }

        // Active primary assignment exists
        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-05-01',
            'is_primary' => true,
        ]);

        $this->service->assertDriverAssignedToVehicle($vehicle->id, $driver->id, '2026-06-01');
        $this->assertTrue(true);
    }

    public function test_assert_driver_is_active_and_substitute_driver(): void
    {
        [$tenant, $branch, $vehicle, $driver] = $this->createTenantFleet();

        $this->service->assertDriverIsActive($driver->id);

        $inactiveDriver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Inactive Driver',
            'phone' => '9888800002',
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->assertDriverIsActive($inactiveDriver->id);
    }

    public function test_assert_substitute_driver_is_valid(): void
    {
        [$tenant, $branch, $vehicle, $driver] = $this->createTenantFleet();

        // Null or empty substitute is valid (no substitute)
        $this->service->assertSubstituteDriverIsValid(null, $driver->id);
        $this->service->assertSubstituteDriverIsValid('', $driver->id);

        // Substitute driver cannot be identical to primary driver
        try {
            $this->service->assertSubstituteDriverIsValid($driver->id, $driver->id);
            $this->fail('Expected ValidationException when substitute is identical to primary');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('substitute_driver_id', $e->errors());
        }
    }

    public function test_assert_route_legs_and_start_station(): void
    {
        // Empty route legs
        try {
            $this->service->assertRouteLegsAreValid([], 1);
            $this->fail('Expected ValidationException for empty route legs');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('route_legs', $e->errors());
        }

        // Null start station
        try {
            $this->service->assertRouteLegsAreValid([['route_order' => 1]], null);
            $this->fail('Expected ValidationException for null start station');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_station_id', $e->errors());
        }
    }

    public function test_assert_meter_readings(): void
    {
        // Start meter missing
        try {
            $this->service->assertStartMeterReadingIsPresent(['start_meter_reading_km' => 0]);
            $this->fail('Expected ValidationException for 0 start meter');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_meter_reading_km', $e->errors());
        }

        // End meter less than start meter
        try {
            $this->service->assertOptionalEndMeterReadingOnCreate([
                'start_meter_reading_km' => 1500,
                'end_meter_reading_km' => 1200,
            ], 'veh-1');
            $this->fail('Expected ValidationException when end meter < start meter');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_meter_reading_km', $e->errors());
        }
    }

    public function test_assert_status_transition(): void
    {
        [$tenant, $branch, $vehicle, $driver] = $this->createTenantFleet();

        $trip = new Trip([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::Pending->value,
            'start_at' => now()->toDateString(),
        ]);

        // Same status transition is allowed (no-op)
        $this->service->assertStatusTransition($trip, TripStatus::Pending->value);

        // Invalid transition: Completed -> Pending
        $trip->status = TripStatus::Completed->value;
        $this->expectException(ValidationException::class);
        $this->service->assertStatusTransition($trip, TripStatus::Pending->value);
    }

    public function test_assert_positive_freight_total(): void
    {
        // Positive total passes
        $this->service->assertPositiveFreightTotal([
            ['freight_amount' => 5000],
            ['freight_amount' => 2000],
        ]);

        // Zero or negative total throws
        $this->expectException(ValidationException::class);
        $this->service->assertPositiveFreightTotal([
            ['freight_amount' => 0],
            ['freight_amount' => 0],
        ]);
    }

    public function test_assert_completion_dates_and_meter_readings_on_complete(): void
    {
        [$tenant, $branch, $vehicle, $driver] = $this->createTenantFleet();

        $trip = new Trip([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::InProgress->value,
            'start_at' => '2026-06-10',
            'end_at' => '2026-06-15',
            'actual_arrival_at' => '2026-06-14',
            'start_meter_reading_km' => 10000,
            'end_meter_reading_km' => 11000,
        ]);

        // Valid dates pass
        $this->service->assertCompletionDatesAreValid($trip);

        // Arrival before start date throws
        try {
            $this->service->assertCompletionDatesAreValid($trip, [
                'actual_arrival_at' => '2026-06-05',
            ]);
            $this->fail('Expected ValidationException when arrival is before start');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('actual_arrival_at', $e->errors());
        }

        // Meter reading on complete valid
        $this->service->assertMeterReadingsOnComplete($trip);

        // End meter less than start meter throws
        try {
            $this->service->assertMeterReadingsOnComplete($trip, [
                'end_meter_reading_km' => 9000,
            ]);
            $this->fail('Expected ValidationException when end meter < start meter');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_meter_reading_km', $e->errors());
        }
    }

    public function test_assert_expenses_are_valid(): void
    {
        // Empty expenses passes
        $this->service->assertExpensesAreValid([], '2026-06-01');

        // Missing trip start date throws
        try {
            $this->service->assertExpensesAreValid([
                ['expense_type' => 'TOLL', 'amount' => 500],
            ], null);
            $this->fail('Expected ValidationException when trip start date is null');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_at', $e->errors());
        }

        // Expense date before trip start throws
        try {
            $this->service->assertExpensesAreValid([
                ['expense_type' => 'TOLL', 'amount' => 500, 'expense_date' => '2026-05-25'],
            ], '2026-06-01', '2026-06-10');
            $this->fail('Expected ValidationException when expense is before trip start');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('expenses.0.expense_date', $e->errors());
        }
    }
}
