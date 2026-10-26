<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\Enums\TripStatus;
use App\Station;
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    private function createTenantAdmin(string $name = 'Test Fleet'): array
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        return [$tenant, $this->assignBranchIfRequired($admin, $tenant)];
    }

    private function createVehicle(Tenant $tenant, int $branchId): Vehicle
    {
        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);

        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'registration_number' => 'MH' . fake()->unique()->numerify('##') . 'AB' . fake()->unique()->numerify('####'),
            'vehicle_type' => 'TRUCK',
            'carrying_capacity' => 16.0,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    private function createDriver(Tenant $tenant): Driver
    {
        return Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Driver ' . fake()->unique()->name(),
            'mobile_number' => fake()->unique()->numerify('9#########'),
            'status' => 'active',
        ]);
    }

    public function test_empty_query_returns_empty_results(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->getJson('/app/api/quick-search?q=');

        $response->assertOk()
            ->assertJson([
                'query' => '',
                'trips' => [],
                'detail' => null,
            ]);
    }

    public function test_search_by_trip_number_returns_matching_trips_and_details(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();
        $vehicle = $this->createVehicle($tenant, $admin->branch_id);
        $driver = $this->createDriver($tenant);

        $station = Station::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mumbai Station',
        ]);

        $trip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $admin->branch_id,
            'trip_number' => 'TRIP-QS-1001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $station->id,
            'start_odometer' => 10000,
            'close_odometer' => 10500,
            'start_at' => now()->subDay(),
            'status' => TripStatus::Completed->value,
        ]);

        TripExpense::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $admin->branch_id,
            'trip_id' => $trip->id,
            'expense_type' => 'toll',
            'amount' => 500,
            'notes' => 'Expressway toll slip',
        ]);

        $response = $this->actingAs($admin)->getJson('/app/api/quick-search?q=TRIP-QS-1001');

        $response->assertOk()
            ->assertJsonPath('query', 'TRIP-QS-1001')
            ->assertJsonPath('trips.0.trip_number', 'TRIP-QS-1001')
            ->assertJsonPath('detail.trip.trip_number', 'TRIP-QS-1001');

        $this->assertNotNull($response->json('detail.expenses'));
        $this->assertArrayHasKey('diesel_performance', $response->json('detail'));
    }

    public function test_search_by_expense_notes_or_type_finds_trip(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();
        $vehicle = $this->createVehicle($tenant, $admin->branch_id);
        $driver = $this->createDriver($tenant);

        $trip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $admin->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-EXPENSE-777',
            'start_at' => now()->subHours(5),
            'status' => TripStatus::InProgress->value,
        ]);

        TripExpense::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $admin->branch_id,
            'trip_id' => $trip->id,
            'expense_type' => 'other',
            'other_type' => 'Police fine',
            'amount' => 200,
            'notes' => 'Highway clearance slip',
        ]);

        $response = $this->actingAs($admin)->getJson('/app/api/quick-search?q=Highway clearance');

        $response->assertOk()
            ->assertJsonPath('trips.0.trip_number', 'TRIP-EXPENSE-777');

        $response2 = $this->actingAs($admin)->getJson('/app/api/quick-search?q=Police fine');
        $response2->assertOk()
            ->assertJsonPath('trips.0.trip_number', 'TRIP-EXPENSE-777');
    }

    public function test_quick_search_enforces_tenant_isolation(): void
    {
        [$tenantA, $adminA] = $this->createTenantAdmin('Tenant A');
        [$tenantB, $adminB] = $this->createTenantAdmin('Tenant B');
        $vehicleB = $this->createVehicle($tenantB, $adminB->branch_id);
        $driverB = $this->createDriver($tenantB);

        Trip::query()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $adminB->branch_id,
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driverB->id,
            'trip_number' => 'SECRET-TRIP-B',
            'start_at' => now(),
            'status' => TripStatus::Pending->value,
        ]);

        $response = $this->actingAs($adminA)->getJson('/app/api/quick-search?q=SECRET-TRIP-B');

        $response->assertOk()
            ->assertJson([
                'trips' => [],
                'detail' => null,
            ]);
    }
}
