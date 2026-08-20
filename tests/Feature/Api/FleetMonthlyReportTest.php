<?php

namespace Tests\Feature\Api;

use App\Challan;
use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\TyrePosition;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripService;
use App\Station;
use App\Support\TenantContext;
use App\Support\TyreLayoutDefaults;
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
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $this->createJuneTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06');

        $response->assertOk();
        $response->assertJsonPath('company_name', 'Fleet Tenant');
        $response->assertJsonPath('data.0.registration_number', 'MH12TR0001');
        $response->assertJsonPath('data.0.driver_name', 'Fleet Driver');
        $response->assertJsonPath('data.0.trip_count', 1);
        $response->assertJsonPath('data.0.total_km', 560);
        $response->assertJsonPath('data.0.diesel_liters', 100);
        $response->assertJsonPath('data.0.average_km_per_liter', 5.6);
        $response->assertJsonPath('data.0.total_expense', 7200);
        $response->assertJsonPath('data.0.total_freight', 18000);
        $response->assertJsonPath('data.0.saving', 10800);
        $response->assertJsonPath('summary.total_bill', 18000);
        $response->assertJsonPath('summary.total_saving', 10800);
        $response->assertJsonPath('summary.rto_entry', 500);
        $response->assertJsonPath('summary.challan_rto', 1500);
        $response->assertJsonPath('summary.net_saving', 10800);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_moderator_can_access_monthly_fleet_report(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('moderator'), 0, 2);
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06')
            ->assertOk();
    }

    public function test_monthly_fleet_report_includes_tyres_used_and_expense_for_truck(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        TyreLayoutDefaults::seedForTenant($tenant->id);
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyres', [
                'serial_number' => 'TYRE-FLEET-1',
                'brand' => 'MRF',
                'size' => '10.00-20',
                'tyre_type' => 'RADIAL',
                'purchase_date' => '2026-06-05',
                'expected_life_km' => 40000,
                'purchase_cost' => 18000,
            ]);

        $tyreId = $create->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-08',
                'km_at_installation' => 1000,
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/api/tyres/{$tyreId}/expenses", [
                'expense_type' => 'MAINTENANCE',
                'amount' => 2500,
                'expense_date' => '2026-06-20',
                'notes' => 'Tyre maintenance',
            ])
            ->assertCreated();

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06');

        $response->assertOk();
        $response->assertJsonPath('data.0.tyres_used', 1);
        $response->assertJsonPath('data.0.tyre_expense', 20500);
        $response->assertJsonPath('summary.total_tyres_used', 1);
        $response->assertJsonPath('summary.tyre_bill', 20500);
    }

    public function test_month_parameter_is_required(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
    }

    public function test_report_attributes_trip_to_earlier_of_actual_arrival_and_end_date(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $ops = $this->operationalAttributesForTenant($tenant);

        Trip::query()->create([
            ...$ops,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-MAY-ARRIVAL',
            'start_station_id' => $stations['home']->id,
            'trip_type' => 'ONE_WAY',
            'status' => TripStatus::Completed->value,
            'start_at' => '2026-05-20 09:00:00',
            'end_at' => '2026-06-01 20:00:00',
            'actual_arrival_at' => '2026-05-31 18:00:00',
            'start_meter_reading_km' => 10000,
            'total_distance_km' => 100,
            'total_freight_amount' => 3000,
            'total_expense_amount' => 0,
            'total_custom_amount' => 0,
        ]);

        Trip::query()->create([
            ...$ops,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-JUNE-END',
            'start_station_id' => $stations['home']->id,
            'trip_type' => 'ONE_WAY',
            'status' => TripStatus::Completed->value,
            'start_at' => '2026-05-28 09:00:00',
            'end_at' => '2026-06-02 20:00:00',
            'actual_arrival_at' => '2026-06-02 18:00:00',
            'start_meter_reading_km' => 10100,
            'total_distance_km' => 100,
            'total_freight_amount' => 5000,
            'total_expense_amount' => 0,
            'total_custom_amount' => 0,
        ]);

        Trip::query()->create([
            ...$ops,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRIP-JULY-ARRIVAL',
            'start_station_id' => $stations['home']->id,
            'trip_type' => 'ONE_WAY',
            'status' => TripStatus::Completed->value,
            'start_at' => '2026-06-20 09:00:00',
            'end_at' => '2026-07-02 20:00:00',
            'actual_arrival_at' => '2026-07-01 18:00:00',
            'start_meter_reading_km' => 10200,
            'total_distance_km' => 100,
            'total_freight_amount' => 7000,
            'total_expense_amount' => 0,
            'total_custom_amount' => 0,
        ]);

        $june = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06')
            ->assertOk();

        $june->assertJsonPath('data.0.trip_count', 1);
        $june->assertJsonPath('summary.total_bill', 5000);

        $may = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-05')
            ->assertOk();

        $may->assertJsonPath('data.0.trip_count', 1);
        $may->assertJsonPath('summary.total_bill', 3000);

        $july = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-07')
            ->assertOk();

        $july->assertJsonPath('data.0.trip_count', 1);
        $july->assertJsonPath('summary.total_bill', 7000);
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'MH12TR0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Fleet Driver',
            'phone' => '9876543210',
            'daily_wage' => 900,
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
                'name' => 'Station A',
            ]),
            'b' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'Station B',
            ]),
        ];

        Challan::query()->create([
            ...$operationalAttributes,
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

        $customer = $this->createReportCustomer($tenant);

        $trip = app(TripService::class)->create([
            'trip_type' => 'ROUND_TRIP',
            'start_meter_reading_km' => 10000,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-06-10 09:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'customer_id' => $customer->id,
                    'sequence_no' => 1,
                    'distance_km' => 100,
                    'weight_ton' => 90,
                    'freight_amount' => 18000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'distance_km' => 100,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
            'expenses' => [
                [
                    'expense_type' => TripExpenseType::Diesel->value,
                    'expense_date' => '2026-06-10',
                    'amount' => 6000,
                    'fuel_liters' => 100,
                    'fuel_odometer_km' => 10000,
                    'fuel_rate' => 60,
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

        $trip->forceFill([
            'end_at' => '2026-06-15 20:00:00',
            'actual_arrival_at' => '2026-06-15 18:00:00',
            'total_distance_km' => 560,
        ])->save();

        return $trip->fresh();
    }

    private function createReportCustomer(Tenant $tenant): Customer
    {
        $city = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();

        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => 'Fleet Report Customer '.fake()->unique()->numerify('####'),
        ]);
    }
}
