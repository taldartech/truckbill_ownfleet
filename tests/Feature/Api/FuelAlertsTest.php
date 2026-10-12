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
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_compliance_alerts_endpoint_returns_fuel_alerts_for_all_four_conditions(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $operational = $this->operationalAttributesForTenant($tenant);

        // Vehicle has 300L fuel tank capacity, default mileage 4.0
        $vehicle->update([
            'fuel_tank_capacity_ltr' => 300.0,
            'default_mileage_km_per_liter' => 4.0,
        ]);

        // Trip 1: Mileage below expected & Abnormal fuel variance
        // Distance: 500 KM. Planned mileage: 5.0 KM/L -> Expected diesel: 100 L
        // Actual diesel: 200 L -> Actual mileage: 2.5 KM/L (< 80% of 5.0).
        // Variance: 200 - 100 = 100 L (>= 50L and 100% >= 25%).
        $trip1 = Trip::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-LOW-MILEAGE',
            'status' => TripStatus::InProgress->value,
            'start_at' => now()->subDays(5),
            'total_distance_km' => 500.0,
            'planned_mileage_km_per_liter' => 5.0,
            'planned_diesel_liters' => 100.0,
        ]);

        TripExpense::query()->create([
            ...$operational,
            'trip_id' => $trip1->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => now()->subDays(5)->toDateString(),
            'fuel_liters' => 200.0,
            'amount' => 19000.0,
            'fuel_odometer_km' => 12500.0,
        ]);

        // Trip 2: Missing odometer & Fuel consumption above threshold (refill exceeds tank capacity of 300L)
        $trip2 = Trip::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-OVERFILL',
            'status' => TripStatus::InProgress->value,
            'start_at' => now()->subDays(2),
            'total_distance_km' => 200.0,
            'planned_mileage_km_per_liter' => 4.0,
            'planned_diesel_liters' => 50.0,
        ]);

        // Refill 1 on Trip 2: 350L (exceeds vehicle 300L tank capacity) and missing odometer (fuel_odometer_km is null)
        TripExpense::query()->create([
            ...$operational,
            'trip_id' => $trip2->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => now()->subDays(2)->toDateString(),
            'fuel_liters' => 350.0,
            'amount' => 33250.0,
            'fuel_odometer_km' => null, // triggers missing_odometer
        ]);

        $response = $this->getJson('/app/dashboard/compliance-alerts');

        $response->assertOk();
        $response->assertJsonStructure([
            'documents',
            'licenses',
            'due_services',
            'eway_bills',
            'fuel_alerts',
            'alerts',
            'requires_company',
        ]);

        $fuelAlerts = $response->json('fuel_alerts');
        $this->assertNotEmpty($fuelAlerts);

        $alertTypes = collect($fuelAlerts)->pluck('alert_type')->all();

        // 1. Mileage below expected
        $this->assertContains('mileage_below_expected', $alertTypes);

        // 2. Abnormal fuel variance
        $this->assertContains('abnormal_fuel_variance', $alertTypes);

        // 3. Fuel consumption above threshold
        $this->assertContains('fuel_consumption_threshold', $alertTypes);

        // 4. Missing odometer
        $this->assertContains('missing_odometer', $alertTypes);

        // Also verify normalized 'alerts' array contains fuel alerts with category 'fuel'
        $normalizedAlerts = $response->json('alerts');
        $fuelNormalized = collect($normalizedAlerts)->where('category', 'fuel');
        $this->assertNotEmpty($fuelNormalized);
        $this->assertTrue($fuelNormalized->contains(fn ($a) => $a['type'] === 'mileage_below_expected'));
        $this->assertTrue($fuelNormalized->contains(fn ($a) => $a['type'] === 'abnormal_fuel_variance'));
        $this->assertTrue($fuelNormalized->contains(fn ($a) => $a['type'] === 'fuel_consumption_threshold'));
        $this->assertTrue($fuelNormalized->contains(fn ($a) => $a['type'] === 'missing_odometer'));
    }

    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Fuel Alert Tenant',
            'slug' => 'fuel-alert-'.fake()->unique()->slug(),
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
            'name' => 'Fleet Alert Manager',
            'email' => 'alertmanager@fueltest.com',
            'password' => bcrypt('secret123'),
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'RJ27FA9999',
            'vehicle_type' => 'TRUCK',
            'fuel_tank_capacity_ltr' => 300.0,
            'default_mileage_km_per_liter' => 4.0,
            'is_active' => true,
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Alert Driver',
            'phone' => '9876500000',
            'is_active' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }
}
