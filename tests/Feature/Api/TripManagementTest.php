<?php

namespace Tests\Feature\Api;

use App\City;
use App\Driver;
use App\DriverAssignment;
use App\Enums\RouteType;
use App\Enums\TripExpenseType;
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

class TripManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_next_trip_number_preview_returns_sequential_format(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/next-number');

        $response->assertOk();
        $response->assertJsonPath('trip_number', 'TRIP-2026-000001');
    }

    public function test_admin_can_create_round_trip_with_route_legs_and_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'end_at' => '2026-06-05 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'route_type' => RouteType::Loaded->value,
                        'sequence_no' => 1,
                        'distance_km' => 150,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['c']->id,
                        'route_type' => RouteType::HalfLoaded->value,
                        'sequence_no' => 2,
                        'distance_km' => 80,
                        'freight_amount' => 12000,
                    ],
                    [
                        'from_station_id' => $stations['c']->id,
                        'to_station_id' => $stations['home']->id,
                        'route_type' => RouteType::Empty->value,
                        'sequence_no' => 3,
                        'distance_km' => 180,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'amount' => 8500,
                        'route_leg_sequence' => 1,
                    ],
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'amount' => 1200,
                    ],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.trip_number', 'TRIP-2026-000001');
        $response->assertJsonPath('data.loaded_distance_km', '150.00');
        $response->assertJsonPath('data.half_loaded_distance_km', '80.00');
        $response->assertJsonPath('data.empty_distance_km', '180.00');
        $response->assertJsonPath('data.total_freight_amount', '37000.00');
        $response->assertJsonPath('data.total_expense_amount', '9700.00');
        $response->assertJsonPath('data.profit_amount', 27300);

        $this->assertDatabaseHas('trips', [
            'tenant_id' => $tenant->id,
            'trip_number' => 'TRIP-2026-000001',
            'start_station_id' => $stations['home']->id,
        ]);

        $this->assertDatabaseCount('trip_route_legs', 3);
        $this->assertDatabaseCount('trip_expenses', 2);
    }

    public function test_moderator_can_view_but_not_create_trips(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('moderator');
        Sanctum::actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'route_type' => RouteType::Loaded->value,
                        'distance_km' => 100,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'route_type' => RouteType::Empty->value,
                        'distance_km' => 100,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_trip_profitability_report_returns_calculated_rows(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('accountant');
        Sanctum::actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/profitability');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['s_no', 'trip_number', 'profit_amount']]]);
    }

    public function test_pending_freight_report_lists_unpaid_loaded_legs(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        Sanctum::actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/pending-freight');

        $response->assertOk();
        $response->assertJsonFragment(['payment_status' => 'partial']);
    }

    public function test_trip_report_export_returns_excel_for_selected_filters(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        Sanctum::actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/api/reports/trips/export?type=profitability&from_date=2026-06-01&to_date=2026-06-30');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_trip_report_export_excludes_trips_outside_date_range(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        Sanctum::actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/profitability?from_date=2026-01-01&to_date=2026-01-31');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Trip Tenant',
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

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TR0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Trip Driver',
            'phone' => '9876543210',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $cityA = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $cityB = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Pune')->firstOrFail();
        $cityC = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Nagpur')->firstOrFail();

        $stations = [
            'home' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'country_id' => $cityA->state->country_id,
                'state_id' => $cityA->state_id,
                'city_id' => $cityA->id,
                'name' => 'Station A',
            ]),
            'b' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'Station B',
            ]),
            'c' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'country_id' => $cityC->state->country_id,
                'state_id' => $cityC->state_id,
                'city_id' => $cityC->id,
                'name' => 'Station C',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createSampleTrip(Tenant $tenant, Vehicle $vehicle, Driver $driver, array $stations): Trip
    {
        TenantContext::setTenantId($tenant->id);

        return app(TripService::class)->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-06-10 09:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'route_type' => RouteType::Loaded->value,
                    'sequence_no' => 1,
                    'distance_km' => 280,
                    'freight_amount' => 18000,
                    'advance_received' => 5000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'route_type' => RouteType::Empty->value,
                    'sequence_no' => 2,
                    'distance_km' => 280,
                ],
            ],
            'expenses' => [
                [
                    'expense_type' => TripExpenseType::Diesel->value,
                    'amount' => 6000,
                ],
            ],
        ]);
    }
}
