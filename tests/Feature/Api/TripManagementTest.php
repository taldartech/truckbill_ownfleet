<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Modules\TripManagement\Services\TripService;
use App\Station;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs($user);

        $branchId = (int) BranchContext::branchId();
        $expectedTripNumber = $this->formatTripNumber($branchId);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/next-number');

        $response->assertOk();
        $response->assertJsonPath('trip_number', $expectedTripNumber);
    }

    public function test_trip_numbers_are_scoped_per_branch_within_same_company(): void
    {
        [$tenant, , $vehicle, $driver, $stations] = $this->createFixtures('admin');

        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        $firstBranchId = $operationalAttributes['branch_id'];

        $superAdmin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $secondBranch = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Second Branch',
            'head_office' => false,
        ]);

        $secondVehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $secondBranch->id,
            'registration_number' => 'MH12TR0002',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $secondDriver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $secondBranch->id,
            'name' => 'Second Branch Driver',
            'phone' => '9876543211',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $secondBranch->id,
            'vehicle_id' => $secondVehicle->id,
            'driver_id' => $secondDriver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $cityA = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $cityB = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Pune')->firstOrFail();

        $secondBranchStations = [
            'home' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $secondBranch->id,
                'country_id' => $cityA->state->country_id,
                'state_id' => $cityA->state_id,
                'city_id' => $cityA->id,
                'name' => 'Second Branch Station A',
            ]),
            'b' => Station::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $secondBranch->id,
                'country_id' => $cityB->state->country_id,
                'state_id' => $cityB->state_id,
                'city_id' => $cityB->id,
                'name' => 'Second Branch Station B',
            ]),
        ];

        $firstTripNumber = $this->formatTripNumber($firstBranchId);

        $this->actingAs($superAdmin);
        $this->enterBranchForSuperAdmin($superAdmin, Branch::query()->findOrFail($firstBranchId));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $firstBranchId)
            ->postJson('/api/trips', $this->sampleTripPayload($vehicle, $driver, $stations))
            ->assertCreated()
            ->assertJsonPath('data.trip_number', $firstTripNumber);

        $this->enterBranchForSuperAdmin($superAdmin, $secondBranch);

        $secondTripNumber = $this->formatTripNumber((int) $secondBranch->id);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $secondBranch->id)
            ->postJson('/api/trips', $this->sampleTripPayload($secondVehicle, $secondDriver, $secondBranchStations, startAt: '2026-06-11 09:00:00'))
            ->assertCreated()
            ->assertJsonPath('data.trip_number', $secondTripNumber);

        $this->assertNotSame($firstTripNumber, $secondTripNumber);
        $this->assertSame($firstTripNumber, $this->formatTripNumber($firstBranchId));
        $this->assertSame($secondTripNumber, $this->formatTripNumber((int) $secondBranch->id));
    }

    public function test_admin_can_create_round_trip_with_route_legs_and_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $branchId = (int) BranchContext::branchId();
        $expectedTripNumber = $this->formatTripNumber($branchId);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'end_at' => '2026-06-05 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_rate' => 250,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['c']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 48,
                        'freight_rate' => 250,
                        'freight_amount' => 12000,
                    ],
                    [
                        'from_station_id' => $stations['c']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 3,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-02',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 12450,
                        'route_leg_sequence' => 1,
                    ],
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-01',
                        'amount' => 1200,
                    ],
                ],
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.trip_number', $expectedTripNumber);
        $response->assertJsonPath('data.start_meter_reading_km', '10000.00');
        $response->assertJsonPath('data.total_freight_amount', '37000.00');
        $response->assertJsonPath('data.total_expense_amount', '9700.00');
        $response->assertJsonPath('data.profit_amount', 27300);

        $this->assertDatabaseHas('trips', [
            'tenant_id' => $tenant->id,
            'trip_number' => $expectedTripNumber,
            'start_station_id' => $stations['home']->id,
            'start_meter_reading_km' => '10000.00',
        ]);

        $this->assertDatabaseCount('trip_route_legs', 3);
        $this->assertDatabaseCount('trip_expenses', 2);
        $dieselExpense = TripExpense::query()->where('expense_type', TripExpenseType::Diesel->value)->first();
        $this->assertNotNull($dieselExpense);
        $this->assertSame('2026-06-02', $dieselExpense->expense_date?->toDateString());
        $this->assertSame('12450.00', (string) $dieselExpense->fuel_odometer_km);
    }

    public function test_diesel_expense_meter_reading_must_stay_within_trip_meter_range(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'end_meter_reading_km' => 10500,
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 9999,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'end_meter_reading_km' => 10500,
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10600,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'end_meter_reading_km' => 10500,
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10200,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.expenses.0.fuel_odometer_km', '10200.00');
    }

    public function test_trip_number_uses_branch_trip_sequence_start(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $branchId = (int) BranchContext::branchId();
        Branch::query()->whereKey($branchId)->update(['trip_sequence_start' => 10000]);

        $expectedTripNumber = $this->formatTripNumber($branchId, 10000);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $this->sampleTripPayload($vehicle, $driver, $stations))
            ->assertCreated()
            ->assertJsonPath('data.trip_number', $expectedTripNumber);
    }

    public function test_other_expense_stores_custom_type_and_notes_separately(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'end_at' => '2026-06-05 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Other->value,
                        'other_type' => 'Vehicle washing',
                        'notes' => 'Paid at highway stop',
                        'expense_date' => '2026-06-02',
                        'amount' => 500,
                    ],
                ],
            ]));

        $response->assertCreated();

        $this->assertDatabaseHas('trip_expenses', [
            'tenant_id' => $tenant->id,
            'expense_type' => TripExpenseType::Other->value,
            'other_type' => 'Vehicle washing',
            'notes' => 'Paid at highway stop',
            'amount' => '500.00',
        ]);
    }

    public function test_expense_date_must_stay_within_trip_window(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $payload = array_merge($this->requiredTripMeterFields(), [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-06-01 08:00:00',
            'end_at' => '2026-06-05 20:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 100,
                    'freight_amount' => 25000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
            'expenses' => [
                [
                    'expense_type' => TripExpenseType::Toll->value,
                    'expense_date' => '2026-05-31',
                    'amount' => 500,
                ],
            ],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.expense_date']);

        $payload['expenses'][0]['expense_date'] = '2026-06-06';

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.expense_date']);
    }

    public function test_can_create_trip_without_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $payload = $this->sampleTripPayload($vehicle, $driver, $stations);
        unset($payload['expenses']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_expense_amount', '0.00');

        $this->assertDatabaseCount('trip_expenses', 0);
    }

    public function test_can_update_trip_expenses_only(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1500,
                    ],
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 7200,
                        'route_leg_sequence' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '8700.00');

        $this->assertDatabaseCount('trip_expenses', 2);
    }

    public function test_cannot_update_route_when_leg_has_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $createResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-10 09:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 90,
                        'freight_rate' => 200,
                        'freight_amount' => 18000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 900,
                        'route_leg_sequence' => 1,
                    ],
                ],
            ]))
            ->assertCreated();

        $tripId = $createResponse->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['c']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 90,
                        'freight_rate' => 200,
                        'freight_amount' => 18000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['route_legs']);
    }

    public function test_admin_can_update_expenses_on_completed_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['status' => TripStatus::Completed->value]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Parking->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 300,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '300.00');
    }

    public function test_trip_can_store_substitute_driver_on_create_and_update(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $substitute = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Substitute Driver',
            'phone' => '9876543211',
            'daily_wage' => 800,
            'is_active' => true,
        ]);

        $createResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'substitute_driver_id' => $substitute->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.substitute_driver_id', $substitute->id);
        $createResponse->assertJsonPath('data.substitute_driver.name', 'Substitute Driver');

        $tripId = $createResponse->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
                'substitute_driver_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.substitute_driver_id', null);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
                'substitute_driver_id' => $driver->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['substitute_driver_id']);
    }

    public function test_trip_search_matches_substitute_driver_name(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $substitute = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ajay Desai',
            'phone' => '9876543211',
            'daily_wage' => 800,
            'is_active' => true,
        ]);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $trip->update([
            'substitute_driver_id' => $substitute->id,
            'trip_number' => 'TRIP-2026-SUB-SEARCH',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?search=Ajay')
            ->assertOk()
            ->assertJsonPath('data.0.trip_number', 'TRIP-2026-SUB-SEARCH')
            ->assertJsonPath('data.0.substitute_driver.name', 'Ajay Desai');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?search='.urlencode($driver->name))
            ->assertOk()
            ->assertJsonPath('data.0.trip_number', 'TRIP-2026-SUB-SEARCH');
    }

    public function test_trip_search_ignores_date_range(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $trip->update([
            'start_at' => '2026-01-15 08:00:00',
            'trip_number' => 'TRIP-2026-000099',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?from_date=2026-06-01&to_date=2026-06-30&search=TRIP-2026-000099')
            ->assertOk()
            ->assertJsonPath('data.0.trip_number', 'TRIP-2026-000099');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?from_date=2026-06-01&to_date=2026-06-30')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_trip_list_can_filter_by_trip_type(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(TripType::OneWay->value), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-02 08:00:00',
                'end_at' => '2026-06-02 20:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                ],
            ]))
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-12 08:00:00',
                'route_legs' => $this->roundTripRouteLegs($stations),
            ]))
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?trip_type=ONE_WAY&from_date=2026-06-01&to_date=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip_type', TripType::OneWay->value);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?trip_type=ROUND_TRIP&from_date=2026-06-01&to_date=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip_type', TripType::RoundTrip->value);
    }

    public function test_moderator_can_create_trips_but_not_delete(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('moderator');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-02 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 50,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson('/api/trips/'.$trip->id)
            ->assertForbidden();
    }

    public function test_trip_profitability_report_returns_calculated_rows(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/profitability');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['s_no', 'trip_number', 'profit_amount']],
            'summary' => ['trip_count'],
        ]);
        $response->assertJsonPath('summary.trip_count', 1);
    }

    public function test_vehicle_wise_trip_report_includes_trip_count(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/vehicle-wise');

        $response->assertOk();
        $response->assertJsonPath('data.0.trip_count', 1);
        $response->assertJsonPath('summary.trip_count', 1);
    }

    public function test_expense_summary_report_includes_trip_count(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/expense-summary');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['trip_count', 'entry_count', 'total_amount']]]);
    }

    public function test_route_profitability_report_uses_trip_meter_distance_when_leg_distance_is_zero(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()->json('data.id');

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
            ->assertOk();

        $report = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/route-profitability?from_date=2026-06-01&to_date=2026-06-30');

        $report->assertOk();
        $report->assertJsonPath('summary.trip_count', 1);

        $rows = collect($report->json('data'));
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn (array $row): bool => (float) $row['total_distance_km'] === 250.0));
        $this->assertSame(500.0, (float) $rows->sum('total_distance_km'));
    }

    public function test_trip_show_includes_diesel_performance_summary(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'planned_mileage_km_per_liter' => 2.75,
                'max_load_ton' => 22,
                'planned_diesel_liters' => 200,
                'diesel_rate_per_liter' => 100,
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()->json('data.id');

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
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/trips/{$tripId}")
            ->assertOk()
            ->assertJsonPath('data.diesel_performance.expected_total_km', 550)
            ->assertJsonPath('data.diesel_performance.actual_total_km', 500)
            ->assertJsonPath('data.diesel_performance.performance_status', 'under')
            ->assertJsonPath('data.diesel_performance.unused_km', 50)
            ->assertJsonPath('data.diesel_performance.rate_per_km', 36.36)
            ->assertJsonPath('data.diesel_performance.remaining_amount', 1818);
    }

    public function test_trip_persists_snapshot_fields_and_runtime_load_classification(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $vehicle->update([
            'default_mileage_km_per_liter' => 2.5,
            'max_load_ton' => 22,
        ]);

        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'planned_mileage_km_per_liter' => 2.5,
                'max_load_ton' => 22,
                'planned_diesel_liters' => 180,
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 10,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.planned_mileage_km_per_liter', '2.50')
            ->assertJsonPath('data.max_load_ton', '22.000')
            ->assertJsonPath('data.route_legs.0.load_classification.category', 'half')
            ->assertJsonPath('data.route_legs.1.load_classification.category', 'empty');
    }

    public function test_trip_report_export_returns_excel_for_selected_filters(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
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
        $this->actingAs($user);
        $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/profitability?from_date=2026-01-01&to_date=2026-01-31');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_create_trip_requires_positive_total_freight(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['route_legs']);
    }

    public function test_create_trip_always_stores_created_status(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'status' => 'IN_PROGRESS',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 50,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_vehicle_for_trip_selection_returns_eligibility_metadata(): void
    {
        [$tenant, $user, $vehicle, $driver] = array_slice($this->createFixtures('admin'), 0, 4);
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/vehicles/for-trip-selection?search=MH12');

        $response->assertOk();
        $response->assertJsonPath('data.0.registration_number', 'MH12TR0001');
        $response->assertJsonPath('data.0.odometer_km', 1000);
        $response->assertJsonPath('data.0.selectable', true);
        $response->assertJsonPath('data.0.primary_driver_name', 'Trip Driver');
    }

    public function test_update_trip_prohibits_vehicle_and_driver_changes(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $otherVehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TR0002',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 500,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'vehicle_id' => $otherVehicle->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_update_trip_allows_meter_reading_changes_on_edit(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'start_meter_reading_km' => 15000,
                'end_meter_reading_km' => 15500,
            ])
            ->assertOk()
            ->assertJsonPath('data.start_meter_reading_km', '15000.00')
            ->assertJsonPath('data.end_meter_reading_km', '15500.00')
            ->assertJsonPath('data.total_distance_km', '500.00');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'start_meter_reading_km' => '15000.00',
            'end_meter_reading_km' => '15500.00',
            'total_distance_km' => '500.00',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'start_meter_reading_km' => 16000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_meter_reading_km']);
    }

    public function test_update_trip_succeeds_when_driver_assignment_changed_but_start_date_unchanged(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $replacementDriver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Replacement Driver',
            'phone' => '9876543211',
            'daily_wage' => 950,
            'is_active' => true,
        ]);

        TenantContext::setTenantId($tenant->id);
        DriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('driver_id', $driver->id)
            ->update(['assigned_to' => '2026-06-09']);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $replacementDriver->id,
            'assigned_from' => '2026-06-09',
            'is_primary' => true,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'start_at' => '2026-06-10',
                'start_station_id' => $stations['home']->id,
            ]);

        $response->assertOk();
    }

    public function test_update_trip_allows_start_date_change_without_driver_revalidation(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        TenantContext::setTenantId($tenant->id);
        DriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->update(['assigned_from' => '2026-06-08']);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'start_at' => '2026-06-07',
                'start_station_id' => $stations['home']->id,
            ]);

        $response->assertOk();
    }

    public function test_create_trip_resolves_primary_driver_on_trip_start_date(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        TenantContext::setTenantId($tenant->id);
        DriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->update(['assigned_from' => '2026-06-08']);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-07 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 50,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_created_trip_cannot_transition_directly_to_cancelled(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => 'CANCELLED',
                'cancelled_at' => '2026-06-11 10:00:00',
                'cancellation_reason' => 'Test cancel',
            ]);

        $response->assertUnprocessable();
    }

    public function test_cancel_in_progress_trip_requires_end_meter_and_syncs_vehicle_odometer(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Cancelled->value,
                'cancelled_at' => '2026-06-11 10:00:00',
                'cancellation_reason' => 'Customer cancelled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_meter_reading_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Cancelled->value,
                'cancelled_at' => '2026-06-11 10:00:00',
                'cancellation_reason' => 'Customer cancelled',
                'end_meter_reading_km' => 10500,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TripStatus::Cancelled->value)
            ->assertJsonPath('data.end_meter_reading_km', '10500.00')
            ->assertJsonPath('data.total_distance_km', '500.00');

        $vehicle->refresh();
        $this->assertSame('10500', (string) $vehicle->odometer_km);
    }

    public function test_check_trip_window_reports_vehicle_overlap_for_open_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $existingTrip = app(TripService::class)->create(array_merge($this->requiredTripMeterFields(), [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-05-01 08:00:00',
            'end_at' => '2026-05-10 20:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 50,
                    'freight_amount' => 5000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
        ]));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/check-window?'.http_build_query([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_at' => '2026-05-05',
                'end_at' => '2026-05-15',
            ]))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('conflict_type', 'vehicle')
            ->assertJsonPath('trip_number', $existingTrip->trip_number);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/check-window?'.http_build_query([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_at' => '2026-05-05',
                'end_at' => '2026-05-15',
                'ignore_trip_id' => $existingTrip->id,
            ]))
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_check_trip_window_allows_dates_after_open_trip_ends(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        app(TripService::class)->create(array_merge($this->requiredTripMeterFields(), [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-05-01 08:00:00',
            'end_at' => '2026-05-10 20:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 50,
                    'freight_amount' => 5000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
        ]));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/check-window?'.http_build_query([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_at' => '2026-05-11',
                'end_at' => '2026-05-20',
            ]))
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_check_trip_window_blocks_future_dates_when_open_trip_has_no_end_date(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $openTrip = app(TripService::class)->create(array_merge($this->requiredTripMeterFields(), [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-07-06 08:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 50,
                    'freight_amount' => 5000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
        ]));

        $openTrip->update(['status' => 'IN_PROGRESS']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/check-window?'.http_build_query([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_at' => '2026-08-01',
                'end_at' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('trip_number', $openTrip->trip_number);
    }

    public function test_check_trip_window_ignores_cancelled_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $cancelledTrip = app(TripService::class)->create(array_merge($this->requiredTripMeterFields(), [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => '2026-05-01 08:00:00',
            'end_at' => '2026-05-10 20:00:00',
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 50,
                    'freight_amount' => 5000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
        ]));

        $cancelledTrip->update([
            'status' => 'CANCELLED',
            'cancelled_at' => '2026-05-02 10:00:00',
            'cancellation_reason' => 'Customer cancelled',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/check-window?'.http_build_query([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_at' => '2026-05-05',
                'end_at' => '2026-05-15',
            ]))
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_new_trip_allowed_when_previous_trip_is_completed(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $completedTrip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $completedTrip->update([
            'status' => 'COMPLETED',
            'end_at' => '2026-06-10 20:00:00',
            'actual_arrival_at' => '2026-06-10 20:00:00',
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-10 09:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 50,
                        'freight_amount' => 5000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $response->assertCreated();
    }

    public function test_total_distance_km_comes_from_meter_readings_not_route_legs(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                    [
                        'from_station_id' => $stations['b']->id,
                        'to_station_id' => $stations['home']->id,
                        'sequence_no' => 2,
                        'weight_ton' => 0,
                        'freight_amount' => 0,
                    ],
                ],
            ]));

        $tripId = $response->assertCreated()->json('data.id');

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
            ->assertOk()
            ->assertJsonPath('data.total_distance_km', '500.00');
    }

    public function test_complete_trip_requires_end_meter_and_syncs_vehicle_odometer(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-11 20:00:00',
                'actual_arrival_at' => '2026-06-11 20:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_meter_reading_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-11 20:00:00',
                'actual_arrival_at' => '2026-06-11 20:00:00',
                'end_meter_reading_km' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_meter_reading_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-11 20:00:00',
                'actual_arrival_at' => '2026-06-11 20:00:00',
                'end_meter_reading_km' => 10500,
            ])
            ->assertOk()
            ->assertJsonPath('data.end_meter_reading_km', '10500.00');

        $vehicle->refresh();
        $this->assertSame('10500', (string) $vehicle->odometer_km);
    }

    public function test_complete_trip_rejects_end_date_before_actual_arrival(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-10 20:00:00',
                'actual_arrival_at' => '2026-06-11 20:00:00',
                'end_meter_reading_km' => 10500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_one_way_trip_skips_return_to_start_validation(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(TripType::OneWay->value), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                ],
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.trip_type', TripType::OneWay->value);
    }

    public function test_round_trip_still_requires_return_to_start(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->requiredTripMeterFields(), [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'start_station_id' => $stations['home']->id,
                'start_at' => '2026-06-01 08:00:00',
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'sequence_no' => 1,
                        'weight_ton' => 100,
                        'freight_amount' => 25000,
                    ],
                ],
            ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['route_legs']);
    }

    public function test_admin_can_soft_delete_created_trip_and_records_deleted_by(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson('/api/trips/'.$trip->id)
            ->assertNoContent();

        $this->assertNull(Trip::query()->find($trip->id));

        $deletedTrip = Trip::query()->withTrashed()->find($trip->id);

        $this->assertNotNull($deletedTrip);
        $this->assertNotNull($deletedTrip->deleted_at);
        $this->assertSame($user->id, $deletedTrip->deleted_by);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips')
            ->assertOk()
            ->assertJsonMissing(['id' => $trip->id]);
    }

    public function test_admin_cannot_delete_completed_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['status' => TripStatus::Completed->value]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson('/api/trips/'.$trip->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trip']);

        $this->assertNotNull(Trip::query()->find($trip->id));
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
            'name' => 'Trip Driver',
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
        $cityC = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Nagpur')->firstOrFail();

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
            'c' => Station::query()->create([
                ...$operationalAttributes,
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
     * @return list<array<string, mixed>>
     */
    private function roundTripRouteLegs(array $stations, float $loadedFreight = 25000): array
    {
        return [
            [
                'from_station_id' => $stations['home']->id,
                'to_station_id' => $stations['b']->id,
                'sequence_no' => 1,
                'weight_ton' => 100,
                'freight_rate' => $loadedFreight / 100,
                'freight_amount' => $loadedFreight,
            ],
            [
                'from_station_id' => $stations['b']->id,
                'to_station_id' => $stations['home']->id,
                'sequence_no' => 2,
                'weight_ton' => 0,
                'freight_amount' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredTripMeterFields(string $tripType = 'ROUND_TRIP', float $startMeter = 10000): array
    {
        return [
            'trip_type' => $tripType,
            'start_meter_reading_km' => $startMeter,
        ];
    }

    /**
     * @param  array<string, Station>  $stations
     * @return array<string, mixed>
     */
    private function sampleTripPayload(
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        string $startAt = '2026-06-10 09:00:00',
    ): array {
        $expenseDate = \Illuminate\Support\Carbon::parse($startAt)->toDateString();

        return [
            ...$this->requiredTripMeterFields(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'start_at' => $startAt,
            'route_legs' => [
                [
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'sequence_no' => 1,
                    'weight_ton' => 90,
                    'freight_rate' => 200,
                    'freight_amount' => 18000,
                ],
                [
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'sequence_no' => 2,
                    'weight_ton' => 0,
                    'freight_amount' => 0,
                ],
            ],
            'expenses' => [
                [
                    'expense_type' => TripExpenseType::Diesel->value,
                    'expense_date' => $expenseDate,
                    'amount' => 6000,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createSampleTrip(Tenant $tenant, Vehicle $vehicle, Driver $driver, array $stations): Trip
    {
        TenantContext::setTenantId($tenant->id);

        return app(TripService::class)->create($this->sampleTripPayload($vehicle, $driver, $stations));
    }
}
