<?php

namespace Tests\Feature\Api;

use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripBiltyService;
use App\Station;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripPickup;
use App\TripRouteLeg;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPointTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_can_create_multiple_pickup_multiple_drop_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 10,
                    'consignor' => 'Consignor A',
                    'goods_name' => 'Steel',
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 6,
                            'freight_amount' => 6000,
                            'consignee' => 'Consignee B',
                            'distance_km' => 120.5,
                            'ewaybill_no' => 'EWB123456789012',
                            'ewaybill_expiry' => '2026-08-20',
                            'no_of_units' => 24,
                            'goods_value' => 150000.50,
                        ],
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 4,
                            'freight_amount' => 4000,
                            'consignee' => 'Consignee C',
                        ],
                    ],
                ],
                [
                    'station_id' => $stations['b']->id,
                    'weight_ton' => 5,
                    'consignor' => 'Consignor B',
                    'goods_name' => 'Cement',
                    'drops' => [
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 5,
                            'freight_amount' => 5000,
                            'consignee' => 'Consignee C2',
                        ],
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()
            ->assertJsonPath('data.trip_type', TripType::MultiPoint->value)
            ->assertJsonPath('data.customer_id', $customer->id)
            ->json('data.id');

        $this->assertDatabaseCount('trip_pickups', 2);
        $this->assertDatabaseCount('trip_route_legs', 3);

        $this->assertEqualsWithDelta(15000.0, (float) Trip::query()->findOrFail($tripId)->total_freight_amount, 0.01);

        $show = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/trips/{$tripId}")
            ->assertOk();

        $show->assertJsonPath('data.pickups.0.pickup_code', 'P001');
        $show->assertJsonPath('data.pickups.0.drops.0.drop_code', 'D001');
        $show->assertJsonPath('data.pickups.0.drops.0.ewaybill_no', 'EWB123456789012');
        $show->assertJsonPath('data.pickups.0.drops.0.no_of_units', 24);
        $this->assertEqualsWithDelta(150000.5, (float) $show->json('data.pickups.0.drops.0.goods_value'), 0.01);
        $this->assertEqualsWithDelta(120.5, (float) $show->json('data.pickups.0.drops.0.distance_km'), 0.01);
        $show->assertJsonPath('data.pickups.0.delivery_status', 'delivered');
        $show->assertJsonPath('data.pickups.1.pickup_code', 'P002');
    }

    public function test_multiple_pickups_can_drop_at_same_station(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 4,
                    'drops' => [
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 4,
                            'freight_amount' => 4000,
                        ],
                    ],
                ],
                [
                    'station_id' => $stations['b']->id,
                    'weight_ton' => 3,
                    'drops' => [
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 3,
                            'freight_amount' => 3000,
                        ],
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()->json('data.id');

        $legs = TripRouteLeg::query()->where('trip_id', $tripId)->orderBy('sequence_no')->get();
        $this->assertCount(2, $legs);
        $this->assertTrue($legs->every(fn (TripRouteLeg $leg): bool => (int) $leg->to_station_id === (int) $stations['c']->id));
    }

    public function test_single_pickup_multiple_drops(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 9,
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 5,
                            'freight_amount' => 5000,
                        ],
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 4,
                            'freight_amount' => 4000,
                        ],
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()->json('data.id');

        $this->assertDatabaseCount('trip_pickups', 1);
        $this->assertSame(2, TripRouteLeg::query()->where('trip_id', $tripId)->count());
    }

    public function test_partial_allocation_blocks_completion(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $tripId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 10,
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 6,
                            'freight_amount' => 6000,
                        ],
                    ],
                ],
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/trips/{$tripId}")
            ->assertOk()
            ->assertJsonPath('data.pickups.0.delivery_status', 'partial')
            ->assertJsonPath('data.pickups.0.remaining_weight_ton', 4);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-05 20:00:00',
                'actual_arrival_at' => '2026-06-05 20:00:00',
                'end_meter_reading_km' => 10500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickups.0.weight_ton']);
    }

    public function test_same_station_allowed_on_multiple_pickups_and_drops(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 2,
                    'drops' => [
                        [
                            'to_station_id' => $stations['home']->id,
                            'weight_ton' => 2,
                            'freight_amount' => 2000,
                        ],
                    ],
                ],
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 3,
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 3,
                            'freight_amount' => 3000,
                        ],
                    ],
                ],
            ]))
            ->assertCreated();
    }

    public function test_drop_weight_exceeding_pickup_is_rejected(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 5,
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 6,
                            'freight_amount' => 6000,
                        ],
                    ],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickups.0.weight_ton']);
    }

    public function test_separate_bilty_per_pickup_drop_leg(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $tripId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->multiPointPayload($vehicle, $driver, $stations, $customer, [
                [
                    'station_id' => $stations['home']->id,
                    'weight_ton' => 8,
                    'drops' => [
                        [
                            'to_station_id' => $stations['b']->id,
                            'weight_ton' => 5,
                            'freight_amount' => 5500.5,
                        ],
                        [
                            'to_station_id' => $stations['c']->id,
                            'weight_ton' => 3,
                            'freight_amount' => 3300.25,
                        ],
                    ],
                ],
            ]))
            ->assertCreated()
            ->json('data.id');

        $trip = Trip::query()->with(['routeLegs.pickup', 'routeLegs.customer', 'vehicle', 'driver'])->findOrFail($tripId);
        $groups = app(TripBiltyService::class)->groupsForTrip($trip);

        $this->assertCount(2, $groups);
        $this->assertEqualsWithDelta(5500.5, (float) $groups[0]['freight_amount'], 0.01);
        $this->assertEqualsWithDelta(3300.25, (float) $groups[1]['freight_amount'], 0.01);
        $this->assertSame('P001', $groups[0]['pickup_code']);
        $this->assertSame('D001', $groups[0]['drop_code']);
        $this->assertSame('D002', $groups[1]['drop_code']);
    }

    public function test_customer_is_required_for_multi_point(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures();
        $this->actingAs($user);

        $payload = $this->multiPointPayload($vehicle, $driver, $stations, null, [
            [
                'station_id' => $stations['home']->id,
                'weight_ton' => 5,
                'drops' => [
                    [
                        'to_station_id' => $stations['b']->id,
                        'weight_ton' => 5,
                        'freight_amount' => 5000,
                    ],
                ],
            ],
        ]);
        unset($payload['customer_id']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_one_way_and_round_trip_still_work(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customer] = $this->createFixtures();
        $this->actingAs($user);

        $oneWay = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', [
                'trip_type' => TripType::OneWay->value,
                'start_meter_reading_km' => 10000,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'end_at' => '2026-06-03 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'customer_id' => $customer->id,
                        'sequence_no' => 1,
                        'distance_km' => 100,
                        'weight_ton' => 20,
                        'freight_amount' => 10000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.trip_type', TripType::OneWay->value);

        $this->assertNull(TripRouteLeg::query()->find($oneWay->json('data.route_legs.0.id'))?->trip_pickup_id);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', [
                'trip_type' => TripType::RoundTrip->value,
                'start_meter_reading_km' => 10000,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-07-10 08:00:00',
                'end_at' => '2026-07-12 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'customer_id' => $customer->id,
                        'sequence_no' => 1,
                        'distance_km' => 100,
                        'weight_ton' => 20,
                        'freight_amount' => 12000,
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
            ])
            ->assertCreated()
            ->assertJsonPath('data.trip_type', TripType::RoundTrip->value);

        $this->assertSame(0, TripPickup::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $pickups
     * @return array<string, mixed>
     */
    private function multiPointPayload(
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        ?Customer $customer,
        array $pickups,
        string $startAt = '2026-06-01 08:00:00',
    ): array {
        return [
            'trip_type' => TripType::MultiPoint->value,
            'start_meter_reading_km' => 10000,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer?->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => $startAt,
            'pickups' => $pickups,
        ];
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>, 5: Customer}
     */
    private function createFixtures(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Multi Point Tenant',
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
            'user_role_id' => UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'MH12MP0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Multi Point Driver',
            'phone' => '9876543201',
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
        $cityC = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Nagpur')->firstOrFail();

        $stations = [
            'home' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityA->state->country_id,
                'state_id' => $cityA->state_id,
                'city_id' => $cityA->id,
                'name' => 'MP Station A',
            ]),
            'b' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'MP Station B',
            ]),
            'c' => Station::query()->create([
                ...$operationalAttributes,
                'country_id' => $cityC->state->country_id,
                'state_id' => $cityC->state_id,
                'city_id' => $cityC->id,
                'name' => 'MP Station C',
            ]),
        ];

        $customer = Customer::query()->create([
            ...$operationalAttributes,
            'country_id' => $cityA->state->country_id,
            'state_id' => $cityA->state_id,
            'city_id' => $cityA->id,
            'name' => 'Multi Point Customer',
        ]);

        TenantContext::setTenantId($tenant->id);

        return [$tenant, $user, $vehicle, $driver, $stations, $customer];
    }
}
