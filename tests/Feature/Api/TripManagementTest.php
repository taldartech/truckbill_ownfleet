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
use App\TripAuditLog;
use App\TripExpense;
use App\TripRouteLeg;
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
        $this->assertNotNull($dieselExpense->trip_route_leg_id);
        $response->assertJsonPath('data.expenses.0.route_leg_sequence', 1);
    }

    public function test_stop_expense_route_leg_sequence_is_persisted_and_returned(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::LoadingCharges->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1000,
                        'route_leg_sequence' => 1,
                    ],
                    [
                        'expense_type' => TripExpenseType::UnloadingCharges->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 999.99,
                        'route_leg_sequence' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.expenses.0.route_leg_sequence', 1)
            ->assertJsonPath('data.expenses.1.route_leg_sequence', 1);

        $loadingExpense = TripExpense::query()
            ->where('trip_id', $trip->id)
            ->where('expense_type', TripExpenseType::LoadingCharges->value)
            ->first();

        $this->assertNotNull($loadingExpense?->trip_route_leg_id);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.expenses.0.route_leg_sequence', 1)
            ->assertJsonPath('data.expenses.1.route_leg_sequence', 1);
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
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km'])
            ->assertJsonFragment([
                'Meter reading cannot be less than Trip Start Meter Reading (10000 KM).',
            ]);

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
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km'])
            ->assertJsonFragment([
                'Meter reading cannot be greater than Trip End Meter Reading (10500 KM).',
            ]);

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

    public function test_diesel_expense_validation_rules(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '0.00');

        $this->assertDatabaseCount('trip_expenses', 0);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1000,
                        'fuel_liters' => 50,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km', 'expenses.0.fuel_rate']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 0,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_liters']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 5001,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_liters']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 12.222,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_liters']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 50,
                        'fuel_rate' => 0,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_rate']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 50,
                        'fuel_rate' => 1001,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_rate']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1,
                        'fuel_liters' => 50,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100.5,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 99999,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.expenses.0.amount', '8500.00');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 3400,
                        'fuel_liters' => 40,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 1700,
                        'fuel_liters' => 20,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '5100.00');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 425000,
                        'fuel_liters' => 5000,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 12000,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.expenses.0.fuel_odometer_km', '12000.00');
    }

    public function test_legacy_diesel_expense_outside_meter_range_can_be_saved_when_unchanged(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        TripExpense::query()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => '2026-06-10',
            'amount' => 8500,
            'fuel_liters' => 100,
            'fuel_rate' => 85,
            'fuel_odometer_km' => 10600,
            'currency' => 'INR',
        ]);

        $trip->update(['end_meter_reading_km' => 10500]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
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
            ->assertOk()
            ->assertJsonPath('data.expenses.0.fuel_odometer_km', '10600.00');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10650,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.fuel_odometer_km']);
    }

    public function test_diesel_expense_date_must_stay_within_trip_window(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        $trip->update(['end_at' => '2026-06-05 20:00:00']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Diesel->value,
                        'expense_date' => '2026-05-31',
                        'amount' => 8500,
                        'fuel_liters' => 100,
                        'fuel_rate' => 85,
                        'fuel_odometer_km' => 10100,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expenses.0.expense_date']);
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
                        'fuel_liters' => 80,
                        'fuel_rate' => 90,
                        'fuel_odometer_km' => 10100,
                        'route_leg_sequence' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '8700.00');

        $this->assertDatabaseCount('trip_expenses', 2);
    }

    public function test_updating_trip_without_expenses_preserves_existing_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $createResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->sampleTripPayload($vehicle, $driver, $stations), [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 900,
                    ],
                ],
            ]))
            ->assertCreated();

        $tripId = $createResponse->json('data.id');
        $expenseId = $createResponse->json('data.expenses.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
                'notes' => 'Updated trip notes only',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_expense_amount', '900.00')
            ->assertJsonPath('data.notes', 'Updated trip notes only');

        $this->assertDatabaseHas('trip_expenses', [
            'id' => $expenseId,
            'amount' => '900.00',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('trip_expenses', 1);
    }

    public function test_updating_trip_expenses_soft_deletes_previous_rows(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);

        $createResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips', array_merge($this->sampleTripPayload($vehicle, $driver, $stations), [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 500,
                    ],
                ],
            ]))
            ->assertCreated();

        $tripId = $createResponse->json('data.id');
        $originalExpenseId = $createResponse->json('data.expenses.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$tripId}", [
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

        $this->assertSoftDeleted('trip_expenses', ['id' => $originalExpenseId]);
        $this->assertDatabaseCount('trip_expenses', 2);
        $this->assertSame(1, TripExpense::query()->where('trip_id', $tripId)->count());
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

    public function test_moderator_can_view_and_create_trips_but_not_archive(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('moderator');
        $this->actingAs($user);
        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

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
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Created by mistake during testing.',
            ])
            ->assertForbidden();
    }

    public function test_moderator_cannot_update_trip_or_view_archived_list(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('moderator');
        $this->actingAs($user);
        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/api/trips/'.$trip->id, [
                'notes' => 'Moderator should not update this trip.',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?archived=archived')
            ->assertForbidden();
    }

    public function test_trip_archive_impact_returns_summary_and_expense_breakdown(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);
        $operationalAttributes = $this->operationalAttributesForTenant($tenant);

        TripRouteLeg::query()->create([
            ...$operationalAttributes,
            'trip_id' => $trip->id,
            'from_station_id' => $stations['home']->id,
            'to_station_id' => $stations['b']->id,
            'from_location' => $stations['home']->name,
            'to_location' => $stations['b']->name,
            'sequence_no' => 1,
            'weight_ton' => 10,
            'freight_amount' => 5000,
        ]);

        TripExpense::query()->create([
            ...$operationalAttributes,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => '2026-06-10',
            'amount' => 5000,
        ]);
        TripExpense::query()->create([
            ...$operationalAttributes,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'expense_date' => '2026-06-10',
            'amount' => 250,
        ]);
        TripExpense::query()->create([
            ...$operationalAttributes,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Other->value,
            'other_type' => 'Parking',
            'expense_date' => '2026-06-10',
            'amount' => 100,
        ]);
        $trip->update(['total_expense_amount' => 5350]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/'.$trip->id.'/archive-impact')
            ->assertOk()
            ->assertJsonPath('trip.trip_number', $trip->trip_number)
            ->assertJsonPath('trip.status_label', 'Draft')
            ->assertJsonPath('linked_records.expenses_count', 3)
            ->assertJsonPath('linked_records.total_expense_amount', 5350)
            ->assertJsonPath('warnings.has_financial_records', true)
            ->assertJsonPath('can_archive', true);
    }

    public function test_admin_cannot_archive_trip_without_archive_reason(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archive_reason']);

        $this->assertNotNull(Trip::query()->find($trip->id));
    }

    public function test_admin_can_archive_in_progress_trip_with_reason(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['status' => TripStatus::InProgress->value]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Running trip should be removable when required.',
            ])
            ->assertNoContent();

        $this->assertNull(Trip::query()->find($trip->id));
    }

    public function test_admin_can_archive_cancelled_trip_with_reason(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);
        $trip->update([
            'status' => TripStatus::Cancelled->value,
            'cancellation_reason' => 'Customer cancelled',
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Cancelled trip no longer needed in system.',
            ])
            ->assertNoContent();

        $this->assertNull(Trip::query()->find($trip->id));
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
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);

        TripExpense::query()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'expense_date' => '2026-06-10',
            'amount' => 500,
            'currency' => 'INR',
        ]);

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

    public function test_vehicle_for_trip_selection_lists_selectable_trucks_first(): void
    {
        [$tenant, $user, , $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $operationalAttributes = $this->operationalAttributesForTenant($tenant);

        $availableVehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'RJ27AV6000',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 500,
            'created_at' => now()->subDay(),
        ]);

        DriverAssignment::query()->create([
            ...$operationalAttributes,
            'vehicle_id' => $availableVehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $scheduledVehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'RJ27AV9004',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 700,
            'created_at' => now(),
        ]);

        $scheduledDriver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Scheduled Driver',
            'phone' => '9876543299',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            ...$operationalAttributes,
            'vehicle_id' => $scheduledVehicle->id,
            'driver_id' => $scheduledDriver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->createSampleTrip($tenant, $scheduledVehicle, $scheduledDriver, $stations, '2026-06-01 08:00:00');

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/vehicles/for-trip-selection?'.http_build_query([
                'search' => 'RJ27',
                'include_ineligible' => 1,
                'as_of_date' => '2026-06-01',
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.registration_number', 'RJ27AV6000');
        $response->assertJsonPath('data.0.selectable', true);
        $response->assertJsonPath('data.1.registration_number', 'RJ27AV9004');
        $response->assertJsonPath('data.1.selectable', false);
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

    public function test_complete_trip_requires_start_date(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['start_at' => null]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/trips/{$trip->id}", [
                'status' => TripStatus::Completed->value,
                'end_at' => '2026-06-11 20:00:00',
                'actual_arrival_at' => '2026-06-11 20:00:00',
                'end_meter_reading_km' => 10500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at']);
    }

    public function test_complete_trip_allows_future_start_and_completion_dates(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-07-03 10:00:00');

        try {
            [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
            $this->actingAs($user);
            $trip = $this->createSampleTrip($tenant, $vehicle, $driver, $stations);
            $trip->update(['start_at' => '2026-07-11 08:00:00']);

            $this->withHeader('X-Tenant-Id', $tenant->id)
                ->putJson("/api/trips/{$trip->id}", ['status' => TripStatus::InProgress->value])
                ->assertOk();

            $this->withHeader('X-Tenant-Id', $tenant->id)
                ->putJson("/api/trips/{$trip->id}", [
                    'status' => TripStatus::Completed->value,
                    'end_at' => '2026-07-11 20:00:00',
                    'actual_arrival_at' => '2026-07-11 20:00:00',
                    'end_meter_reading_km' => 10500,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', TripStatus::Completed->value);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
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

    public function test_admin_can_archive_created_trip_and_records_archived_by(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Created by mistake during testing.',
            ])
            ->assertNoContent();

        $this->assertNull(Trip::query()->find($trip->id));

        $archivedTrip = Trip::query()->withTrashed()->find($trip->id);

        $this->assertNotNull($archivedTrip);
        $this->assertNotNull($archivedTrip->deleted_at);
        $this->assertSame($user->id, $archivedTrip->archived_by);
        $this->assertSame('Created by mistake during testing.', $archivedTrip->archive_reason);

        $this->assertDatabaseHas('trip_audit_logs', [
            'trip_id' => $trip->id,
            'event_type' => 'trip.archived',
            'performed_by' => $user->id,
        ]);

        $auditLog = TripAuditLog::query()->where('trip_id', $trip->id)->first();
        $this->assertNotNull($auditLog);
        $this->assertSame('Created by mistake during testing.', $auditLog->payload['archive_reason'] ?? null);
        $this->assertSame($trip->trip_number, $auditLog->payload['trip_number'] ?? null);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips')
            ->assertOk()
            ->assertJsonMissing(['id' => $trip->id]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips?archived=archived')
            ->assertOk()
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('data.0.archive_reason', 'Created by mistake during testing.');
    }

    public function test_admin_cannot_archive_completed_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['status' => TripStatus::Completed->value]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/'.$trip->id.'/archive-impact')
            ->assertOk()
            ->assertJsonPath('can_archive', false)
            ->assertJsonPath('block_reason', 'Only Super Admin can archive or delete completed trips.');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Completed trip entered by mistake during testing.',
            ])
            ->assertForbidden();

        $this->assertNotNull(Trip::query()->find($trip->id));
    }

    public function test_super_admin_can_archive_completed_trip_with_reason(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('super_admin');
        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, \App\Branch::query()->findOrFail($vehicle->branch_id));
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);
        $trip->update(['status' => TripStatus::Completed->value]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $vehicle->branch_id)
            ->getJson('/api/trips/'.$trip->id.'/archive-impact')
            ->assertOk()
            ->assertJsonPath('can_archive', true);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $vehicle->branch_id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Completed trip removed by super admin during testing.',
            ])
            ->assertNoContent();

        $this->assertNull(Trip::query()->find($trip->id));
    }

    public function test_archived_trip_cannot_be_updated(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Archived trip should not be editable.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/api/trips/'.$trip->id, [
                'notes' => 'Attempt to modify archived trip.',
            ])
            ->assertNotFound();
    }

    public function test_archived_trip_cannot_change_status(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Archived trip should not allow status changes.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/api/trips/'.$trip->id, [
                'status' => TripStatus::InProgress->value,
            ])
            ->assertNotFound();
    }

    public function test_archived_trip_cannot_modify_expenses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Archived trip should not allow expense edits.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/api/trips/'.$trip->id, [
                'expenses' => [
                    [
                        'expense_type' => TripExpenseType::Toll->value,
                        'expense_date' => '2026-06-10',
                        'amount' => 100,
                    ],
                ],
            ])
            ->assertNotFound();
    }

    public function test_archived_trip_can_still_be_viewed(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Archived trip remains readable for audit.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/'.$trip->id)
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.archive_reason', 'Archived trip remains readable for audit.');
    }

    public function test_archived_trip_archive_impact_returns_not_found(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        TenantContext::setTenantId($tenant->id);

        $trip = $this->createPersistedTrip($tenant, $vehicle, $driver, $stations);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/'.$trip->id.'/archive', [
                'archive_reason' => 'Already archived trip should not re-archive.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/trips/'.$trip->id.'/archive-impact')
            ->assertNotFound();
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
        ];
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createPersistedTrip(
        Tenant $tenant,
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        TripStatus $status = TripStatus::Created,
    ): Trip {
        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        TenantContext::setTenantId($tenant->id);

        return Trip::query()->create([
            ...$operationalAttributes,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $stations['home']->id,
            'trip_number' => 'TRP-DEL-'.fake()->unique()->numerify('####'),
            'trip_type' => TripType::OneWay->value,
            'status' => $status->value,
            'start_at' => '2026-06-10 09:00:00',
            'start_meter_reading_km' => 1000,
            'total_freight_amount' => 10000,
            'total_expense_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createSampleTrip(
        Tenant $tenant,
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        string $startAt = '2026-06-10 09:00:00',
    ): Trip {
        TenantContext::setTenantId($tenant->id);

        return app(TripService::class)->create($this->sampleTripPayload($vehicle, $driver, $stations, $startAt));
    }
}
