<?php

namespace Tests\Feature\Api;

use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripService;
use App\Station;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleUtilizationReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_user_can_view_vehicle_utilization_report(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $trip = $this->createTrip($tenant, $vehicle, $driver, $stations, [
            'status' => TripStatus::Completed->value,
            'start_at' => now()->subDays(5)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->subDays(3)->setTime(18, 0)->toDateTimeString(),
            'freight_amount' => 25000,
            'diesel_amount' => 8000,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization?from_date='.now()->startOfMonth()->toDateString().'&to_date='.now()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('company_name', 'Utilization Test Tenant');
        $response->assertJsonStructure([
            'period' => ['from_date', 'to_date', 'period_days'],
            'company_name',
            'summary' => [
                'total_fleet',
                'on_trip_count',
                'available_count',
                'idle_count',
                'idle_7plus_count',
                'maintenance_count',
                'inactive_count',
                'total_trips',
                'total_running_days',
                'total_available_days',
                'total_revenue',
                'total_expenses',
                'total_profit',
                'total_km',
                'overall_utilization_rate',
            ],
            'data' => [
                '*' => [
                    's_no',
                    'vehicle_id',
                    'registration_number',
                    'driver_name',
                    'current_location',
                    'last_trip_number',
                    'last_trip_end',
                    'last_trip_route',
                    'idle_since',
                    'idle_days',
                    'status',
                    'status_label',
                    'status_tier',
                    'trips_count',
                    'running_days',
                    'utilization_rate',
                    'revenue',
                    'expenses',
                    'profit',
                    'total_km',
                ],
            ],
            'meta',
        ]);

        $response->assertJsonPath('data.0.registration_number', 'RJ14TR9999');
        $response->assertJsonPath('data.0.driver_name', 'Test Driver');
        $response->assertJsonPath('data.0.last_trip_number', $trip->trip_number);
        $response->assertJsonPath('data.0.trips_count', 1);
        $response->assertJsonPath('data.0.revenue', 25000);
        $response->assertJsonPath('data.0.expenses', 4500);
        $response->assertJsonPath('data.0.profit', 20500);
        $response->assertJsonPath('summary.total_fleet', 1);
        $response->assertJsonPath('summary.total_revenue', 25000);
        $response->assertJsonPath('summary.total_expenses', 4500);
        $response->assertJsonPath('summary.total_profit', 20500);
    }

    public function test_user_can_filter_vehicle_utilization_report_by_status(): void
    {
        [$tenant, $user, $vehicle1, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        // Vehicle 2: In-Progress trip
        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        $vehicle2 = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'RJ14TR8888',
            'vehicle_type' => 'TRAILER',
            'odometer_km' => 5000,
        ]);

        DriverAssignment::query()->create([
            ...$operationalAttributes,
            'vehicle_id' => $vehicle2->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->createTrip($tenant, $vehicle2, $driver, $stations, [
            'status' => TripStatus::InProgress->value,
            'start_at' => now()->subDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => null,
            'freight_amount' => 15000,
            'diesel_amount' => 3000,
        ]);

        // Filter by on_trip
        $resOnTrip = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization?status=on_trip');
        $resOnTrip->assertOk();
        $this->assertCount(1, $resOnTrip->json('data'));
        $this->assertEquals('RJ14TR8888', $resOnTrip->json('data.0.registration_number'));

        // Filter by available
        $resAvailable = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization?status=available');
        $resAvailable->assertOk();
        $this->assertCount(1, $resAvailable->json('data'));
        $this->assertEquals('RJ14TR9999', $resAvailable->json('data.0.registration_number'));
    }

    public function test_user_can_export_vehicle_utilization_report(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $this->createTrip($tenant, $vehicle, $driver, $stations, [
            'status' => TripStatus::Completed->value,
            'start_at' => now()->subDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->subDay()->setTime(18, 0)->toDateTimeString(),
            'freight_amount' => 10000,
            'diesel_amount' => 2000,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/api/reports/vehicle-utilization/export');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    public function test_user_can_filter_by_search_query_and_idle_days(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        // Search match
        $resMatch = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization?search=RJ14TR9999');
        $resMatch->assertOk();
        $this->assertCount(1, $resMatch->json('data'));

        // Search no match
        $resNoMatch = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization?search=NONEXISTENT_TRUCK');
        $resNoMatch->assertOk();
        $this->assertCount(0, $resNoMatch->json('data'));
    }

    public function test_unauthorized_user_cannot_view_or_export(): void
    {
        [$tenant, $adminUser, $vehicle, $driver, $stations] = $this->createFixtures('admin');

        // Create role without trip_reports permission
        $limitedRole = UserRole::query()->create(['name' => 'limited_staff']);
        $limitedUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Limited User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => $limitedRole->id,
        ]);

        $this->actingAs($limitedUser);
        Sanctum::actingAs($limitedUser);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/vehicle-utilization')
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/api/reports/vehicle-utilization/export')
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Utilization Test Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
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

        $operationalAttributes = $this->operationalAttributesForTenant($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $operationalAttributes['branch_id'],
            'name' => 'Utilization User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'RJ14TR9999',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Test Driver',
            'phone' => '9876543211',
            'daily_wage' => 800,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            ...$operationalAttributes,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $cityA = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $cityB = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Pune')->firstOrFail();

        $stations = [
            'home' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityA->state->country_id,
                'state_id' => $cityA->state_id,
                'city_id' => $cityA->id,
                'name' => 'Station Mumbai',
            ]),
            'b' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'Station Pune',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function createTrip(Tenant $tenant, Vehicle $vehicle, Driver $driver, array $stations, array $options = []): Trip
    {
        TenantContext::setTenantId($tenant->id);

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'country_id' => $stations['home']->country_id,
            'state_id' => $stations['home']->state_id,
            'city_id' => $stations['home']->city_id,
            'name' => 'Test Customer '.fake()->unique()->numerify('####'),
        ]);

        $freight = $options['freight_amount'] ?? 10000;
        $diesel = $options['diesel_amount'] ?? 2000;
        $status = $options['status'] ?? TripStatus::Completed->value;

        $trip = app(TripService::class)->create([
            'trip_type' => 'ONE_WAY',
            'start_meter_reading_km' => 1000,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => $options['start_at'] ?? now()->subDays(2)->toDateTimeString(),
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'customer_id' => $customer->id,
                    'sequence_no' => 1,
                    'distance_km' => 150,
                    'weight_ton' => 20,
                    'freight_amount' => $freight,
                ],
            ],
            'expenses' => [
                [
                    'expense_type' => TripExpenseType::Diesel->value,
                    'expense_date' => now()->toDateString(),
                    'amount' => $diesel,
                    'fuel_liters' => 50,
                    'fuel_odometer_km' => 1050,
                    'fuel_rate' => 90,
                ],
            ],
        ]);

        $trip->forceFill([
            'status' => $status,
            'end_at' => $options['end_at'] ?? ($status === TripStatus::Completed->value ? now()->subDay()->toDateTimeString() : null),
            'actual_arrival_at' => $options['end_at'] ?? ($status === TripStatus::Completed->value ? now()->subDay()->toDateTimeString() : null),
            'total_distance_km' => 150,
        ])->save();

        return $trip->fresh();
    }
}
