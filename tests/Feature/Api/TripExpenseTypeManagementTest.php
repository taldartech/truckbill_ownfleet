<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\Customer;
use App\Enums\TripType;
use App\Station;
use App\Support\BranchContext;
use App\Support\SelectedCompanySession;
use App\Support\TenantContext;
use App\Tenant;
use App\TripExpenseTypeMaster;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripExpenseTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_sees_seeded_default_trip_expense_types(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');

        $response = $this->actingAs($admin)
            ->getJson('/app/api/trip-expense-types?all=1');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(11, count($response->json('data')));

        $this->assertDatabaseHas('trip_expense_types', [
            'code' => 'DIESEL',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_new_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');

        $response = $this->actingAs($admin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Driver Bata',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'DRIVER_BATA');
        $response->assertJsonPath('data.name', 'Driver Bata');
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('trip_expense_types', [
            'code' => 'DRIVER_BATA',
            'name' => 'Driver Bata',
            'is_active' => true,
        ]);
    }

    public function test_cannot_create_duplicate_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        // First creation succeeds
        $this->actingAs($admin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Driver Bata',
            ])
            ->assertCreated();

        // Duplicate name is rejected
        $response = $this->actingAs($admin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Driver Bata',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_edit_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = TripExpenseTypeMaster::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Driver Incentive',
            'code' => 'DRIVER_INCENTIVE',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/app/api/trip-expense-types/{$type->id}", [
                'name' => 'Driver Bonus / Incentive',
                'is_active' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Driver Bonus / Incentive');

        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $type->id,
            'name' => 'Driver Bonus / Incentive',
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = TripExpenseTypeMaster::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loading Charges Extra',
            'code' => 'LOADING_CHARGES_EXTRA',
            'is_active' => true,
        ]);

        // Deactivate via DELETE endpoint
        $this->actingAs($admin)
            ->deleteJson("/app/api/trip-expense-types/{$type->id}")
            ->assertOk();

        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $type->id,
            'is_active' => false,
        ]);

        // Filter active_only should not contain it
        $this->actingAs($admin)
            ->getJson('/app/api/trip-expense-types?active_only=1')
            ->assertOk()
            ->assertJsonMissing(['code' => 'LOADING_CHARGES_EXTRA']);

        // Reactivate via PUT
        $this->actingAs($admin)
            ->putJson("/app/api/trip-expense-types/{$type->id}", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $type->id,
            'is_active' => true,
        ]);
    }

    public function test_only_active_types_are_available_during_trip_expense_creation(): void
    {
        [$tenant, $admin, $vehicle, , $stations] = $this->createFixtures('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        // Deactivate TOLL
        $toll = TripExpenseTypeMaster::query()
            ->where('code', 'TOLL')
            ->first();

        if (! $toll) {
            $toll = TripExpenseTypeMaster::query()->create([
                'name' => 'Toll',
                'code' => 'TOLL',
                'is_active' => false,
            ]);
        } else {
            $toll->update(['is_active' => false]);
        }

        // Creating a trip with deactivated expense type should fail validation
        $response = $this->actingAs($admin)
            ->postJson('/app/api/trips', [
                'vehicle_id' => $vehicle->id,
                'trip_type' => TripType::OneWay->value,
                'start_station_id' => $stations['home']->id,
                'start_at' => now()->toDateString(),
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'freight_amount' => 5000,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => 'TOLL',
                        'expense_date' => now()->toDateString(),
                        'amount' => 300,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['expenses.0.expense_type']);
    }

    public function test_existing_trip_expense_records_remain_unaffected_when_type_deactivated(): void
    {
        [$tenant, $admin, $vehicle, , $stations] = $this->createFixtures('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        // Create a trip with TOLL when TOLL is active
        $createRes = $this->actingAs($admin)
            ->postJson('/app/api/trips', [
                'vehicle_id' => $vehicle->id,
                'trip_type' => TripType::OneWay->value,
                'start_station_id' => $stations['home']->id,
                'start_at' => now()->toDateString(),
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'freight_amount' => 5000,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => 'TOLL',
                        'expense_date' => now()->toDateString(),
                        'amount' => 300,
                    ],
                ],
            ]);

        $createRes->assertCreated();
        $tripId = $createRes->json('data.id');

        // Deactivate TOLL in the master
        $tollMaster = TripExpenseTypeMaster::query()
            ->where('code', 'TOLL')
            ->first();
        $tollMaster->update(['is_active' => false]);

        // Verify existing trip expense record remains unaffected and still has TOLL
        $this->assertDatabaseHas('trip_expenses', [
            'trip_id' => $tripId,
            'expense_type' => 'TOLL',
            'amount' => 300,
        ]);

        // Updating the trip while keeping the existing TOLL expense still succeeds
        $updateRes = $this->actingAs($admin)
            ->putJson("/app/api/trips/{$tripId}", [
                'route_legs' => [
                    [
                        'from_station_id' => $stations['home']->id,
                        'to_station_id' => $stations['b']->id,
                        'freight_amount' => 5000,
                    ],
                ],
                'expenses' => [
                    [
                        'expense_type' => 'TOLL',
                        'expense_date' => now()->toDateString(),
                        'amount' => 350,
                    ],
                ],
            ]);

        $updateRes->assertOk();

        // Existing expense record retained its assigned type
        $this->assertDatabaseHas('trip_expenses', [
            'trip_id' => $tripId,
            'expense_type' => 'TOLL',
            'amount' => 350,
            'deleted_at' => null,
        ]);
    }

    public function test_super_admin_can_manage_trip_expense_types_without_branch(): void
    {
        $superAdmin = User::query()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        // Super Admin (no company/branch selected) can list
        $this->actingAs($superAdmin)
            ->getJson('/app/api/trip-expense-types?all=1')
            ->assertOk();

        // Super Admin can create
        $res = $this->actingAs($superAdmin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Fuel Surcharge',
            ]);
        $res->assertCreated();
        $id = $res->json('data.id');

        // Super Admin can edit
        $this->actingAs($superAdmin)
            ->putJson("/app/api/trip-expense-types/{$id}", [
                'name' => 'Fuel Surcharge Extra',
            ])
            ->assertOk();

        // Super Admin can deactivate
        $this->actingAs($superAdmin)
            ->deleteJson("/app/api/trip-expense-types/{$id}")
            ->assertOk();

        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $id,
            'is_active' => false,
        ]);
    }

    public function test_master_expense_types_apply_across_multiple_companies(): void
    {
        $superAdmin = User::query()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin2@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Global Washing',
            ])
            ->assertCreated();

        // Tenant A user sees Global Washing
        [$tenantA, $userA] = $this->createTenantUser('admin');
        $this->actingAs($userA)
            ->getJson('/app/api/trip-expense-types?active_only=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Global Washing']);

        // Tenant B user also sees Global Washing
        [$tenantB, $userB] = $this->createTenantUser('admin');
        $this->actingAs($userB)
            ->getJson('/app/api/trip-expense-types?active_only=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Global Washing']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.fake()->company(),
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
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: mixed, 4: array<string, Station>}
     */
    private function createFixtures(string $role): array
    {
        [$tenant, $user] = $this->createTenantUser($role);
        $attrs = $this->operationalAttributesForTenant($tenant);

        $user->update(['branch_id' => $attrs['branch_id']]);
        BranchContext::setBranchId($attrs['branch_id']);

        $vehicle = Vehicle::query()->create([
            ...$attrs,
            'registration_number' => 'MH12TR'.rand(1000, 9999),
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = \App\Driver::query()->create([
            ...$attrs,
            'name' => 'Test Driver',
            'phone' => '98'.rand(10000000, 99999999),
            'daily_wage' => 800,
            'is_active' => true,
        ]);

        \App\DriverAssignment::query()->create([
            ...$attrs,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $cityA = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->first()
            ?? City::query()->first();
        $cityB = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Pune')->first()
            ?? City::query()->skip(1)->first()
            ?? $cityA;

        $stations = [
            'home' => Station::query()->create([
                ...$attrs,
                'country_id' => $cityA->state->country_id ?? 1,
                'state_id' => $cityA->state_id ?? 1,
                'city_id' => $cityA->id,
                'name' => 'Station Home '.rand(100, 999),
            ]),
            'b' => Station::query()->create([
                ...$attrs,
                'country_id' => $cityB->state->country_id ?? 1,
                'state_id' => $cityB->state_id ?? 1,
                'city_id' => $cityB->id,
                'name' => 'Station Dest '.rand(100, 999),
            ]),
        ];

        return [$tenant, $user, $vehicle, null, $stations];
    }
}
