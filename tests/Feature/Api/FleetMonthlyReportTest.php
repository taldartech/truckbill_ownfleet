<?php

namespace Tests\Feature\Api;

use App\Challan;
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

class FleetMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_view_monthly_fleet_report_with_vehicle_totals(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $this->createJuneTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06');

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Fleet Tenant');
        $response->assertJsonPath('data.rows.0.registration_number', 'MH12TR0001');
        $response->assertJsonPath('data.rows.0.driver_name', 'Fleet Driver');
        $response->assertJsonPath('data.rows.0.trip_count', 1);
        $response->assertJsonPath('data.rows.0.total_km', 560);
        $response->assertJsonPath('data.rows.0.diesel_liters', 100);
        $response->assertJsonPath('data.rows.0.average_km_per_liter', 5.6);
        $response->assertJsonPath('data.rows.0.total_expense', 7200);
        $response->assertJsonPath('data.rows.0.saving', 10800);
        $response->assertJsonPath('data.summary.total_bill', 18000);
        $response->assertJsonPath('data.summary.total_saving', 10800);
        $response->assertJsonPath('data.summary.rto_entry', 500);
        $response->assertJsonPath('data.summary.challan_rto', 1500);
        $response->assertJsonPath('data.summary.net_saving', 10800);
    }

    public function test_moderator_can_view_but_cannot_export_monthly_fleet_report(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('moderator'), 0, 2);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get('/api/reports/fleet-monthly/export?month=2026-06')
            ->assertForbidden();
    }

    public function test_month_parameter_is_required(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Fleet Tenant',
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
            'name' => 'Fleet Driver',
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
        ];

        Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-001',
            'challan_date' => '2026-06-15',
            'violation_type' => 'Over speed',
            'amount' => 1500,
            'status' => 'UNPAID',
        ]);

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createJuneTrip(Tenant $tenant, Vehicle $vehicle, Driver $driver, array $stations): Trip
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
                    'expense_date' => '2026-06-10',
                    'amount' => 6000,
                    'fuel_liters' => 100,
                ],
                [
                    'expense_type' => TripExpenseType::Toll->value,
                    'expense_date' => '2026-06-10',
                    'amount' => 700,
                ],
                [
                    'expense_type' => TripExpenseType::RtoExpense->value,
                    'expense_date' => '2026-06-10',
                    'amount' => 500,
                ],
            ],
        ]);
    }
}
