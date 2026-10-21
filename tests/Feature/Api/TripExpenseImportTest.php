<?php

namespace Tests\Feature\Api;

use App\City;
use App\Driver;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\TenantSubscription;
use App\Station;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TripExpenseImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_analyze_suggests_mappings_for_sample_fastag_csv(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        $this->actingAs($user);

        $csv = implode("\n", [
            'Txn Date,Txn Time,VRN,Debit Amount,Plaza Name,Txn ID',
            '01/08/2024,10:15:00,GJ-01-AB-1234,120.00,Ahmedabad Plaza,TXN1',
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/trips/expense-import/analyze', [
                'import_type' => 'fastag',
                'file' => UploadedFile::fake()->createWithContent('fastag.csv', $csv),
            ]);

        $response->assertOk();
        $response->assertJsonPath('import_type', 'fastag');
        $response->assertJsonPath('suggested.transaction_date', 0);
        $response->assertJsonPath('suggested.vrn', 2);
        $response->assertJsonPath('suggested.debit', 3);
        $this->assertNotEmpty($response->json('fields'));
        $this->assertNotEmpty($response->json('columns'));
    }

    public function test_fastag_preview_filters_matches_and_flags_issues(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);

        $matchedTrip = $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'trip_number' => '2024-25/1',
            'start_at' => '2024-08-01 04:00:00',
            'end_at' => '2024-08-03 20:00:00',
            'actual_arrival_at' => '2024-08-01 18:00:00',
            'status' => TripStatus::InProgress,
        ]);

        TripExpense::query()->create([
            ...$ops,
            'trip_id' => $matchedTrip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'expense_date' => '2024-07-01',
            'amount' => 50,
            'currency' => 'INR',
            'reference' => 'DUP-REF',
            'created_by' => $user->id,
        ]);

        $csv = implode("\n", [
            'Txn Date,Txn Time,VRN,Debit Amount,Plaza Name,Txn ID,Type',
            '01/08/2024,10:15:00,GJ-01-AB-1234,120.50,Ahmedabad Plaza,TXN-OK,DEBIT',
            '02/08/2024,11:00:00,GJ-01-AB-1234,80.00,Other Plaza,TXN-OTHER,DEBIT',
            '01/08/2024,12:00:00,XX99ZZ9999,90.00,Unknown Plaza,TXN-UNK,DEBIT',
            '01/08/2024,09:00:00,GJ-01-AB-1234,70.00,Early Plaza,TXN-EARLY,DEBIT',
            '01/08/2024,15:00:00,GJ-01-AB-1234,60.00,Dup Plaza,DUP-REF,DEBIT',
            '01/08/2024,16:00:00,GJ-01-AB-1234,10.00,Credit Plaza,TXN-CR,CREDIT',
        ]);

        // Early plaza at 09:00 with arrival 18:00 should match; vehicle exists as GJ01AB1234
        $vehicle->forceFill(['registration_number' => 'GJ01AB1234'])->save();

        $map = [
            'transaction_date' => 0,
            'transaction_time' => 1,
            'vrn' => 2,
            'debit' => 3,
            'description' => 4,
            'reference' => 5,
            'type' => 6,
            'date_time' => null,
        ];

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/trips/expense-import/preview', [
                'import_type' => 'fastag',
                'statement_date' => '2024-08-01',
                'header_row' => 1,
                'column_map' => json_encode($map),
                'file' => UploadedFile::fake()->createWithContent('fastag.csv', $csv),
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('other_dates'));
        $this->assertGreaterThanOrEqual(1, $response->json('skipped'));

        $rows = collect($response->json('rows'));
        $this->assertTrue($rows->contains(fn ($row) => $row['reference'] === 'TXN-OK' && $row['trip_id'] === $matchedTrip->id && $row['include'] === true));
        $this->assertTrue($rows->contains(fn ($row) => $row['reference'] === 'TXN-UNK' && $row['issue'] === 'Vehicle not found' && $row['include'] === false));
        $this->assertTrue($rows->contains(fn ($row) => $row['reference'] === 'DUP-REF' && $row['already_imported'] === true && $row['include'] === false));
        $this->assertFalse($rows->contains(fn ($row) => $row['reference'] === 'TXN-CR'));
        $this->assertFalse($rows->contains(fn ($row) => $row['reference'] === 'TXN-OTHER'));

        $normalized = $rows->firstWhere('reference', 'TXN-OK');
        $this->assertSame('GJ01AB1234', $normalized['vrn']);
    }

    public function test_fuel_preview_filters_and_handles_no_vrn_and_trailing_dash_amount(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);
        $vehicle->forceFill(['registration_number' => 'MH12TR0001'])->save();

        $trip = $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'trip_number' => 'FUEL-1',
            'start_at' => '2024-08-01 05:00:00',
            'end_at' => null,
            'actual_arrival_at' => null,
            'status' => TripStatus::InProgress,
        ]);

        $csv = implode("\n", [
            'Date,Time,VRN,Outlet,Volume,Amount,Product,CR/DR,TxnType,Ref',
            '01/08/2024,10:00:00,MH-12-TR-0001,SMART WAY,100,9850-,DIESEL,DR,SALE,F1',
            '01/08/2024,11:00:00,MH-12-TR-0001,PETROL PUMP,40,4000,PETROL,DR,SALE,F2',
            '01/08/2024,12:00:00,MH-12-TR-0001,CREDIT PUMP,50,5000,DIESEL,CR,SALE,F3',
            '01/08/2024,13:00:00,MH-12-TR-0001,RECHARGE PUMP,0,1000,DIESEL,DR,RECHARGE,F4',
            '01/08/2024,14:00:00,,NO VRN PUMP,20,2000,HSD,DR,SALE,F5',
        ]);

        $map = [
            'transaction_date' => 0,
            'transaction_time' => 1,
            'vrn' => 2,
            'pump' => 3,
            'volume' => 4,
            'amount' => 5,
            'product' => 6,
            'credit_debit' => 7,
            'txn_type' => 8,
            'reference' => 9,
            'date_time' => null,
            'rate' => null,
        ];

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/trips/expense-import/preview', [
                'import_type' => 'fuel',
                'statement_date' => '2024-08-01',
                'header_row' => 1,
                'column_map' => json_encode($map),
                'file' => UploadedFile::fake()->createWithContent('fuel.csv', $csv),
            ]);

        $response->assertOk();
        $rows = collect($response->json('rows'));

        $this->assertCount(2, $rows);
        $sale = $rows->firstWhere('reference', 'F1');
        $this->assertNotNull($sale);
        $this->assertSame('9850.00', $sale['amount']);
        $this->assertSame('98.50', $sale['rate']);
        $this->assertSame($trip->id, $sale['trip_id']);
        $this->assertTrue($sale['include']);

        $noVrn = $rows->firstWhere('reference', 'F5');
        $this->assertNotNull($noVrn);
        $this->assertSame('No vehicle on statement — choose a trip', $noVrn['issue']);
        $this->assertFalse($noVrn['include']);
        $this->assertNull($noVrn['trip_id']);
    }

    public function test_store_fastag_creates_expenses_and_skips_duplicates(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);

        $trip = $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'start_at' => '2024-08-01 04:00:00',
            'status' => TripStatus::InProgress,
        ]);

        TripExpense::query()->create([
            ...$ops,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'expense_date' => '2024-08-01',
            'amount' => 10,
            'currency' => 'INR',
            'reference' => 'EXISTS',
            'created_by' => $user->id,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/expense-import', [
                'import_type' => 'fastag',
                'rows' => [
                    [
                        'include' => true,
                        'trip_id' => $trip->id,
                        'transacted_at' => '2024-08-01T10:15',
                        'amount' => '120.00',
                        'plaza' => 'Ahmedabad Plaza',
                        'reference' => 'NEW-1',
                        'remark' => 'ok',
                    ],
                    [
                        'include' => true,
                        'trip_id' => $trip->id,
                        'transacted_at' => '2024-08-01T11:00',
                        'amount' => '50.00',
                        'plaza' => 'Dup',
                        'reference' => 'EXISTS',
                    ],
                    [
                        'include' => true,
                        'trip_id' => $trip->id,
                        'transacted_at' => '2024-08-01T12:00',
                        'amount' => '30.00',
                        'plaza' => 'Batch dup',
                        'reference' => 'NEW-1',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('created', 1);
        $response->assertJsonPath('skipped', 2);

        $this->assertDatabaseHas('trip_expenses', [
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'reference' => 'NEW-1',
            'amount' => '120.00',
        ]);

        $expense = TripExpense::query()->where('reference', 'NEW-1')->first();
        $this->assertStringContainsString('Ahmedabad Plaza at 01/08/2024 10:15', (string) $expense->notes);
        $this->assertStringContainsString('ok', (string) $expense->notes);
        $this->assertSame((string) $user->id, (string) $expense->created_by);
    }

    public function test_store_fuel_creates_diesel_with_pump_in_notes(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);

        $trip = $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'start_at' => '2024-08-01 04:00:00',
            'status' => TripStatus::InProgress,
            'total_expense_amount' => 0,
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/expense-import', [
                'import_type' => 'fuel',
                'rows' => [
                    [
                        'include' => true,
                        'trip_id' => $trip->id,
                        'transacted_at' => '2024-08-01T10:00',
                        'liters' => '100.000',
                        'rate' => '98.50',
                        'amount' => '9850.00',
                        'pump_name' => 'SMART WAY',
                        'reference' => 'FUEL-1',
                        'remark' => '',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('created', 1);

        $expense = TripExpense::query()->where('reference', 'FUEL-1')->first();
        $this->assertNotNull($expense);
        $this->assertSame(TripExpenseType::Diesel->value, $expense->expense_type instanceof \BackedEnum
            ? $expense->expense_type->value
            : (string) $expense->expense_type);
        $this->assertSame('100.00', (string) $expense->fuel_liters);
        $this->assertSame('98.50', (string) $expense->fuel_rate);
        $this->assertSame('9850.00', (string) $expense->amount);
        $this->assertStringContainsString('SMART WAY at 01/08/2024 10:00', (string) $expense->notes);
        $this->assertNull($expense->getAttribute('vendor_id') ?? null);

        $trip->refresh();
        $this->assertSame('9850.00', (string) $trip->total_expense_amount);
    }

    public function test_store_rejects_cancelled_and_other_company_trips(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);

        $cancelled = $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'start_at' => '2024-08-01 04:00:00',
            'status' => TripStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        $otherOps = $this->operationalAttributesForTenant($otherTenant);
        $otherVehicle = Vehicle::query()->create([
            ...$otherOps,
            'registration_number' => 'MH12OT0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);
        $otherDriver = Driver::query()->create([
            ...$otherOps,
            'name' => 'Other Driver',
            'phone' => '9111111111',
            'daily_wage' => 900,
            'is_active' => true,
        ]);
        $city = City::query()->whereHas('state', fn ($q) => $q->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $otherStation = Station::query()->create([
            ...$otherOps,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => 'Other Station',
        ]);
        $foreignTrip = $this->createTrip($otherOps, $otherVehicle, $otherDriver, $otherStation, [
            'start_at' => '2024-08-01 04:00:00',
            'status' => TripStatus::InProgress,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/expense-import', [
                'import_type' => 'fastag',
                'rows' => [[
                    'include' => true,
                    'trip_id' => $cancelled->id,
                    'transacted_at' => '2024-08-01T10:00',
                    'amount' => '100',
                    'plaza' => 'X',
                    'reference' => 'C1',
                ]],
            ])
            ->assertStatus(422);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/trips/expense-import', [
                'import_type' => 'fastag',
                'rows' => [[
                    'include' => true,
                    'trip_id' => $foreignTrip->id,
                    'transacted_at' => '2024-08-01T10:00',
                    'amount' => '100',
                    'plaza' => 'X',
                    'reference' => 'C2',
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_forbidden_without_permission(): void
    {
        [$tenant, $user] = $this->createFixtures('accountant');
        $this->actingAs($user);

        $csv = "Txn Date,VRN,Debit\n01/08/2024,GJ01AB1234,10\n";

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/trips/expense-import/analyze', [
                'import_type' => 'fastag',
                'file' => UploadedFile::fake()->createWithContent('fastag.csv', $csv),
            ])
            ->assertForbidden();
    }

    public function test_fastag_preview_flags_no_trip_when_none_match_window(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        $ops = $this->operationalAttributesForTenant($tenant);
        $vehicle->forceFill(['registration_number' => 'GJ01AB1234'])->save();

        // Trip starts after the txn — should not match
        $this->createTrip($ops, $vehicle, $driver, $stations['home'], [
            'start_at' => '2024-08-01 20:00:00',
            'status' => TripStatus::InProgress,
        ]);

        $csv = implode("\n", [
            'Txn Date,Txn Time,VRN,Debit Amount,Plaza Name,Txn ID',
            '01/08/2024,10:15:00,GJ01AB1234,120.00,Plaza,NO-TRIP',
        ]);

        $map = [
            'transaction_date' => 0,
            'transaction_time' => 1,
            'vrn' => 2,
            'debit' => 3,
            'description' => 4,
            'reference' => 5,
        ];

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/trips/expense-import/preview', [
                'import_type' => 'fastag',
                'statement_date' => '2024-08-01',
                'column_map' => json_encode($map),
                'file' => UploadedFile::fake()->createWithContent('fastag.csv', $csv),
            ]);

        $response->assertOk();
        $row = collect($response->json('rows'))->first();
        $this->assertSame('No trip found for this date & time', $row['issue']);
        $this->assertFalse($row['include']);
    }

    /**
     * @return array{0: Tenant, 1: User, 2?: Vehicle, 3?: Driver, 4?: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Import Tenant',
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

        $ops = $this->operationalAttributesForTenant($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ops['branch_id'],
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        if ($role === 'accountant') {
            return [$tenant, $user];
        }

        $vehicle = Vehicle::query()->create([
            ...$ops,
            'registration_number' => 'MH12TR0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$ops,
            'name' => 'Import Driver',
            'phone' => '9876543210',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        $city = City::query()->whereHas('state', fn ($q) => $q->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();
        $stations = [
            'home' => Station::query()->create([
                ...$ops,
                'country_id' => $city->state->country_id,
                'state_id' => $city->state_id,
                'city_id' => $city->id,
                'name' => 'Home Station',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array{tenant_id: string, branch_id: int}  $ops
     * @param  array<string, mixed>  $overrides
     */
    private function createTrip(array $ops, Vehicle $vehicle, Driver $driver, Station $station, array $overrides = []): Trip
    {
        return Trip::query()->create(array_merge([
            ...$ops,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'start_station_id' => $station->id,
            'trip_number' => 'IMP-'.fake()->unique()->numerify('####'),
            'trip_type' => TripType::RoundTrip->value,
            'status' => TripStatus::InProgress->value,
            'start_at' => '2024-08-01 08:00:00',
            'start_meter_reading_km' => 10000,
            'total_expense_amount' => 0,
            'total_freight_amount' => 0,
        ], $overrides));
    }
}
