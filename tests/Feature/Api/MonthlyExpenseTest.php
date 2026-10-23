<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripService;
use App\MonthlyExpense;
use App\Station;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_crud_monthly_expenses_with_required_validation(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        $this->actingAs($user);

        $this->postJson('/app/api/monthly-expenses', [
            'month' => '2026-08',
            'particulars' => '',
            'amount' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['particulars', 'amount']);

        $this->postJson('/app/api/monthly-expenses', [
            'month' => '2026-08-01',
            'particulars' => 'Office rent',
            'amount' => 2500,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);

        $create = $this->postJson('/app/api/monthly-expenses', [
            'month' => '2026-08',
            'particulars' => 'Office rent',
            'amount' => 2500.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.month', '2026-08')
            ->assertJsonPath('data.particulars', 'Office rent')
            ->assertJsonPath('data.amount', 2500.5);

        $expenseId = $create->json('data.id');

        $this->getJson('/app/api/monthly-expenses?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data.0.particulars', 'Office rent')
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/app/api/monthly-expenses/{$expenseId}", [
            'month' => '2026-08',
            'particulars' => 'Office rent revised',
            'amount' => 3000,
        ])
            ->assertOk()
            ->assertJsonPath('data.particulars', 'Office rent revised')
            ->assertJsonPath('data.amount', 3000);

        $this->deleteJson("/app/api/monthly-expenses/{$expenseId}", [
            'deleted_reason' => 'Expense cancelled',
        ])
            ->assertNoContent();

        $this->assertSoftDeleted('monthly_expenses', ['id' => $expenseId]);
        $this->assertDatabaseHas('monthly_expenses', [
            'tenant_id' => $tenant->id,
            'id' => $expenseId,
        ]);
    }

    public function test_monthly_expenses_are_isolated_by_branch(): void
    {
        [$tenant, $userA] = $this->createFixtures('admin');
        $branchA = Branch::query()->findOrFail($userA->branch_id);
        $branchB = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Second Branch',
            'head_office' => false,
        ]);

        $userB = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchB->id,
            'name' => 'Branch B Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor('admin'),
        ]);

        $this->actingAs($userA)
            ->postJson('/app/api/monthly-expenses', [
                'month' => '2026-08',
                'particulars' => 'Branch A rent',
                'amount' => 1000,
            ])
            ->assertCreated();

        $this->actingAs($userB)
            ->getJson('/app/api/monthly-expenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($userB)
            ->postJson('/app/api/monthly-expenses', [
                'month' => '2026-08',
                'particulars' => 'Branch B rent',
                'amount' => 2000,
            ])
            ->assertCreated();

        $this->actingAs($userA)
            ->getJson('/app/api/monthly-expenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.particulars', 'Branch A rent');

        $this->assertSame($branchA->id, (int) MonthlyExpense::query()->withoutGlobalScopes()->where('particulars', 'Branch A rent')->value('branch_id'));
        $this->assertSame($branchB->id, (int) MonthlyExpense::query()->withoutGlobalScopes()->where('particulars', 'Branch B rent')->value('branch_id'));
    }

    public function test_monthly_expenses_reduce_fleet_report_net_saving_but_not_dashboard_profit(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations] = $this->createFixtures('admin');
        $this->actingAs($user);
        Sanctum::actingAs($user);

        $this->createJuneTrip($tenant, $vehicle, $driver, $stations);

        MonthlyExpense::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'month' => '2026-06',
            'particulars' => 'Yard rent',
            'amount' => 800,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/fleet-monthly?month=2026-06')
            ->assertOk()
            ->assertJsonPath('summary.total_saving', 10800)
            ->assertJsonPath('summary.monthly_expenses', 800)
            ->assertJsonPath('summary.monthly_expense_items.0.particulars', 'Yard rent')
            ->assertJsonPath('summary.monthly_expense_items.0.amount', 800)
            ->assertJsonPath('summary.net_saving', 10000);

        Trip::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-DASH-1',
            'status' => TripStatus::Completed,
            'start_at' => '2026-06-12 08:00:00',
            'end_at' => '2026-06-12 18:00:00',
            'actual_arrival_at' => '2026-06-12 18:00:00',
            'total_freight_amount' => 5000,
            'total_custom_amount' => 0,
            'total_expense_amount' => 1000,
        ]);

        $this->getJson('/app/dashboard/stats?from_date=2026-06-01&to_date=2026-06-30')
            ->assertOk()
            ->assertJsonPath('trips.total_expense_amount', 8200)
            ->assertJsonPath('trips.profit_amount', 14800);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/reports/trips/profitability?from_date=2026-06-01&to_date=2026-06-30')
            ->assertOk()
            ->assertJsonPath('summary.monthly_expenses', 800)
            ->assertJsonPath('summary.total_expense_amount', 8200)
            ->assertJsonPath('summary.profit_amount', 14000);
    }

    public function test_moderator_can_view_and_create_monthly_expenses(): void
    {
        [$tenant, $user] = $this->createFixtures('moderator');

        $this->actingAs($user)
            ->getJson('/app/api/monthly-expenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $create = $this->actingAs($user)
            ->postJson('/app/api/monthly-expenses', [
                'month' => '2026-08',
                'particulars' => 'Yard rent',
                'amount' => 800,
            ])
            ->assertCreated();

        $expenseId = $create->json('data.id');

        $this->assertDatabaseHas('monthly_expenses', [
            'tenant_id' => $tenant->id,
            'id' => $expenseId,
            'month' => '2026-08',
            'particulars' => 'Yard rent',
            'amount' => 800.00,
        ]);

        $this->actingAs($user)
            ->putJson("/app/api/monthly-expenses/{$expenseId}", [
                'month' => '2026-08',
                'particulars' => 'Yard rent updated',
                'amount' => 900,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/app/api/monthly-expenses/{$expenseId}", [
                'deleted_reason' => 'Unauthorized deletion attempt',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('monthly_expenses', [
            'tenant_id' => $tenant->id,
            'id' => $expenseId,
            'particulars' => 'Yard rent',
            'amount' => 800.00,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User, 2?: Vehicle, 3?: Driver, 4?: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Monthly Expense Tenant',
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

        if ($role === 'moderator') {
            return [$tenant, $user];
        }

        $vehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'MH12TR0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Expense Driver',
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

        return [$tenant, $user, $vehicle, $driver, $stations];
    }

    /**
     * @param  array<string, Station>  $stations
     */
    private function createJuneTrip(Tenant $tenant, Vehicle $vehicle, Driver $driver, array $stations): Trip
    {
        TenantContext::setTenantId($tenant->id);
        BranchContext::setBranchId((int) $vehicle->branch_id);

        $city = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => 'Monthly Expense Customer '.fake()->unique()->numerify('####'),
        ]);

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
            'status' => TripStatus::Completed->value,
            'end_at' => '2026-06-15 20:00:00',
            'actual_arrival_at' => '2026-06-15 18:00:00',
            'total_distance_km' => 560,
        ])->save();

        return $trip->fresh();
    }
}
