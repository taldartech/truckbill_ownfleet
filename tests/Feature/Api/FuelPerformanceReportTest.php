<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\UserRoleName;
use App\Models\TenantSubscription;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_fuel_performance_reports_all_dimensions(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $operational = $this->operationalAttributesForTenant($tenant);

        // Trip: 600 KM, Planned Mileage: 4 KM/L (Expected Fuel: 150 L)
        // Actual Fuel: 160 L (Variance: +10 L, Actual Mileage: 3.75 KM/L, Cost: ₹15,200, Cost/KM: ₹25.33)
        $trip = Trip::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-FUEL-01',
            'status' => TripStatus::Completed->value,
            'start_at' => now()->startOfMonth(),
            'end_at' => now()->startOfMonth()->addDays(2),
            'total_distance_km' => 600.0,
            'planned_mileage_km_per_liter' => 4.0,
            'planned_diesel_liters' => 150.0,
            'diesel_rate_per_liter' => 95.0,
            'total_freight_amount' => 30000.0,
            'total_expense_amount' => 15200.0,
        ]);

        TripExpense::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => now()->startOfMonth()->toDateString(),
            'fuel_liters' => 160.0,
            'fuel_rate' => 95.0,
            'amount' => 15200.0,
            'fuel_odometer_km' => 1600.0,
        ]);

        TripRouteLeg::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'from_city' => 'Jaipur',
            'to_city' => 'Delhi',
            'sequence_no' => 1,
            'distance_km' => 600.0,
            'expected_mileage_km_per_liter' => 4.0,
            'freight_amount' => 30000.0,
        ]);

        // 1. Vehicle-wise
        $responseVehicle = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/reports/trips/fuel-performance?dimension=vehicle')
            ->assertOk();

        $responseVehicle->assertJsonPath('data.0.vehicle', 'RJ27TR0001')
            ->assertJsonPath('data.0.trip_count', 1)
            ->assertJsonPath('data.0.total_distance_km', 600)
            ->assertJsonPath('data.0.actual_fuel_liters', 160)
            ->assertJsonPath('data.0.expected_fuel_liters', 150)
            ->assertJsonPath('data.0.fuel_variance_liters', 10)
            ->assertJsonPath('data.0.average_mileage', 3.75)
            ->assertJsonPath('data.0.cost_per_km', 25.33);

        // 2. Trip-wise
        $responseTrip = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/reports/trips/fuel-performance?dimension=trip')
            ->assertOk();

        $responseTrip->assertJsonPath('data.0.trip_number', 'TRIP-FUEL-01')
            ->assertJsonPath('data.0.actual_mileage', 3.75)
            ->assertJsonPath('data.0.expected_mileage', 4)
            ->assertJsonPath('data.0.fuel_variance_liters', 10)
            ->assertJsonPath('data.0.cost_per_km', 25.33);

        // 3. Driver-wise
        $responseDriver = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/reports/trips/fuel-performance?dimension=driver')
            ->assertOk();

        $responseDriver->assertJsonPath('data.0.driver', 'Suresh Driver')
            ->assertJsonPath('data.0.trip_count', 1)
            ->assertJsonPath('data.0.average_mileage', 3.75)
            ->assertJsonPath('data.0.actual_fuel_liters', 160);

        // 4. Route-wise
        $responseRoute = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/reports/trips/fuel-performance?dimension=route')
            ->assertOk();

        $responseRoute->assertJsonPath('data.0.route', 'Jaipur → Delhi')
            ->assertJsonPath('data.0.trip_count', 1)
            ->assertJsonPath('data.0.average_mileage', 3.75)
            ->assertJsonPath('data.0.cost_per_km', 25.33);
    }

    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Fuel Test Fleet',
            'slug' => 'fuel-fleet-'.fake()->unique()->slug(),
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        TenantContext::setTenantId($tenant->id);
        $operational = $this->operationalAttributesForTenant($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $operational['branch_id'],
            'name' => 'Fleet Manager',
            'email' => 'manager@fueltest.com',
            'password' => bcrypt('secret123'),
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'RJ27TR0001',
            'vehicle_type' => 'TRUCK',
            'default_mileage_km_per_liter' => 4.0,
            'fuel_tank_capacity_ltr' => 300.0,
            'is_active' => true,
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Suresh Driver',
            'phone' => '9876543211',
            'license_number' => 'RJ2720230009999',
            'is_active' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }
}
