<?php

namespace Tests\Feature\Api;

use App\Challan;
use App\City;
use App\Driver;
use App\DriverAssignment;
use App\Enums\RouteType;
use App\Enums\TripStatus;
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

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_tenant_scoped_models_return_no_rows_without_tenant_context(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Scoped Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12SC0001',
            'vehicle_type' => 'TRUCK',
        ]);

        TenantContext::setTenantId(null);

        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_compliance_emi_endpoint_requires_permission(): void
    {
        [$tenant, $user, $vehicle] = $this->createVehicleDriverFixtures('moderator');
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/compliance/emis', [
                'vehicle_id' => $vehicle->id,
                'lender_name' => 'HDFC',
                'emi_amount' => 12000,
                'due_date' => '2026-07-01',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_challan_number_is_rejected_per_tenant(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createVehicleDriverFixtures('admin');
        Sanctum::actingAs($user);

        Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-DUP-1',
            'challan_date' => '2026-06-01',
            'violation_type' => 'Over speeding',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-DUP-1',
                'challan_date' => '2026-06-02',
                'violation_type' => 'Signal jump',
                'amount' => 700,
                'status' => 'UNPAID',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challan_number']);
    }

    public function test_trip_creation_requires_driver_assignment_on_start_date(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createTripFixtures('admin', withAssignment: false);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->tripPayload($vehicle, $driver, $stations))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_completed_trip_cannot_be_edited(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createTripFixtures('admin');
        Sanctum::actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = app(TripService::class)->create($this->tripPayload($vehicle, $driver, $stations, asArray: true));

        $trip->update([
            'status' => TripStatus::InProgress,
            'end_at' => '2026-06-05 20:00:00',
            'actual_arrival_at' => '2026-06-05 19:00:00',
        ]);

        $trip->update(['status' => TripStatus::Completed]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'customer_name' => 'Should not update',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trip']);
    }

    public function test_vehicle_cannot_have_two_open_trips(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createTripFixtures('admin');
        Sanctum::actingAs($user);
        TenantContext::setTenantId($tenant->id);

        app(TripService::class)->create($this->tripPayload($vehicle, $driver, $stations, asArray: true));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->tripPayload($vehicle, $driver, $stations))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Readiness Tenant',
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

        return [$tenant, $user];
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver}
     */
    private function createVehicleDriverFixtures(string $role): array
    {
        [$tenant, $user] = $this->createTenantUser($role);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12PR0001',
            'vehicle_type' => 'TRUCK',
        ]);

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Readiness Driver',
            'phone' => '9876500000',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>}
     */
    private function createTripFixtures(string $role, bool $withAssignment = true): array
    {
        [$tenant, $user, $vehicle, $driver] = $this->createVehicleDriverFixtures($role);

        if ($withAssignment) {
            DriverAssignment::query()->create([
                'tenant_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'assigned_from' => '2026-01-01',
                'is_primary' => true,
            ]);
        }

        $cityA = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $cityB = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Pune')->firstOrFail();

        $stations = [
            'home' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'country_id' => $cityA->state->country_id,
                'state_id' => $cityA->state_id,
                'city_id' => $cityA->id,
                'name' => 'Station Home',
            ]),
            'b' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'Station B',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array<string, Station>  $stations
     * @return array<string, mixed>
     */
    private function tripPayload(Vehicle $vehicle, Driver $driver, array $stations, bool $asArray = false): array
    {
        $payload = [
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
                    'to_station_id' => $stations['home']->id,
                    'route_type' => RouteType::Empty->value,
                    'sequence_no' => 2,
                    'distance_km' => 150,
                ],
            ],
        ];

        return $payload;
    }
}
