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
            'super_admin_id' => $admin->resolvedSuperAdminId(),
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
            'super_admin_id' => $admin->resolvedSuperAdminId(),
            'name' => 'Loading Charges Extra',
            'code' => 'LOADING_CHARGES_EXTRA',
            'is_active' => true,
        ]);

        // Deactivate via PUT endpoint
        $this->actingAs($admin)
            ->putJson("/app/api/trip-expense-types/{$type->id}", [
                'is_active' => false,
            ])
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

    public function test_admin_can_soft_delete_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = TripExpenseTypeMaster::query()->create([
            'super_admin_id' => $admin->resolvedSuperAdminId(),
            'name' => 'To Be Deleted',
            'code' => 'TO_BE_DELETED',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/app/api/trip-expense-types/{$type->id}")
            ->assertOk();

        $this->assertSoftDeleted('trip_expense_types', [
            'id' => $type->id,
        ]);
    }

    public function test_can_recreate_soft_deleted_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = TripExpenseTypeMaster::query()->create([
            'super_admin_id' => $admin->resolvedSuperAdminId(),
            'name' => 'Driver Bata',
            'code' => 'DRIVER_BATA',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/app/api/trip-expense-types/{$type->id}")
            ->assertOk();

        $this->assertSoftDeleted('trip_expense_types', [
            'id' => $type->id,
        ]);

        $res = $this->actingAs($admin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Driver Bata',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Driver Bata');

        $newId = $res->json('data.id');
        $this->assertNotEquals($type->id, $newId);
        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $newId,
            'name' => 'Driver Bata',
            'deleted_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_user_without_delete_permission_cannot_delete_trip_expense_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $deletePermission = \App\Permission::query()->where('slug', 'trip_expense_types.delete')->first();
        if ($deletePermission) {
            \Illuminate\Support\Facades\DB::table('permission_user_role')
                ->where('user_role_id', $admin->user_role_id)
                ->where('permission_id', $deletePermission->id)
                ->delete();
            $admin->clearPermissionCache();
        }

        $type = TripExpenseTypeMaster::query()->create([
            'super_admin_id' => $admin->resolvedSuperAdminId(),
            'name' => 'Should Not Delete',
            'code' => 'SHOULD_NOT_DELETE',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/app/api/trip-expense-types/{$type->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('trip_expense_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    }

    public function test_existing_trip_expense_records_remain_unaffected_when_type_deactivated(): void
    {
        [$tenant, $admin, $vehicle, , $stations] = $this->createFixtures('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        TripExpenseTypeMaster::query()->create([
            'name' => 'Toll',
            'code' => 'TOLL',
            'is_active' => true,
        ]);

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

        $this->assertSoftDeleted('trip_expense_types', [
            'id' => $id,
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

        // Tenant A user under superAdmin sees Global Washing
        [$tenantA, $userA] = $this->createTenantUser('admin', $superAdmin);
        $this->actingAs($userA)
            ->getJson('/app/api/trip-expense-types?active_only=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Global Washing']);

        // Tenant B user under superAdmin also sees Global Washing
        [$tenantB, $userB] = $this->createTenantUser('admin', $superAdmin);
        $this->actingAs($userB)
            ->getJson('/app/api/trip-expense-types?active_only=1')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Global Washing']);
    }

    public function test_expense_types_isolated_between_different_super_admins(): void
    {
        $superAdminA = User::query()->create([
            'name' => 'Super Admin A',
            'email' => 'superadminA@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $superAdminB = User::query()->create([
            'name' => 'Super Admin B',
            'email' => 'superadminB@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdminA)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Custom Surcharge A',
            ])
            ->assertCreated();

        // Super Admin B cannot see Super Admin A's custom type
        $resB = $this->actingAs($superAdminB)
            ->getJson('/app/api/trip-expense-types?all=1')
            ->assertOk();
        $namesB = collect($resB->json('data'))->pluck('name')->all();
        $this->assertNotContains('Custom Surcharge A', $namesB);

        // Super Admin B can create their own expense type with the same name without conflict
        $this->actingAs($superAdminB)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Custom Surcharge A',
            ])
            ->assertCreated();
    }

    public function test_distinct_trip_expenses_from_super_admins_companies_populate_expense_types(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin C',
            'email' => 'superadminC@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        [$tenant1, $admin1] = $this->createTenantUser('admin', $superAdmin);
        [$tenant2, $admin2] = $this->createTenantUser('admin', $superAdmin);

        $attrs1 = $this->operationalAttributesForTenant($tenant1);
        $vehicle = Vehicle::query()->create([
            ...$attrs1,
            'registration_number' => 'MH12TR9999',
            'vehicle_type' => 'TRUCK',
        ]);
        $driver = \App\Driver::query()->create([
            ...$attrs1,
            'name' => 'Test Driver C',
            'phone' => '9876543210',
            'daily_wage' => 800,
            'is_active' => true,
        ]);

        $dummyTripId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('trips')->insert([
            'id' => $dummyTripId,
            'tenant_id' => $tenant1->id,
            'branch_id' => $attrs1['branch_id'],
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_type' => 'ONE_WAY',
            'status' => 'PENDING',
            'start_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('trip_expenses')->insert([
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenant1->id,
                'trip_id' => $dummyTripId,
                'expense_type' => 'CRANE_CHARGES',
                'amount' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenant2->id,
                'trip_id' => $dummyTripId,
                'expense_type' => 'WEIGHBRIDGE_FEE',
                'amount' => 150,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Run migration logic again or test backfill
        $migration = require database_path('migrations/2026_10_13_160000_update_trip_expense_types_table_to_use_super_admin_id.php');
        $migration->up();

        // Both distinct types should now exist under $superAdmin->id
        $this->assertDatabaseHas('trip_expense_types', [
            'super_admin_id' => $superAdmin->id,
            'code' => 'CRANE_CHARGES',
        ]);
        $this->assertDatabaseHas('trip_expense_types', [
            'super_admin_id' => $superAdmin->id,
            'code' => 'WEIGHBRIDGE_FEE',
        ]);
    }

    public function test_diesel_and_other_system_types_are_hidden_from_master_and_immutable(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $diesel = TripExpenseTypeMaster::query()->create([
            'name' => 'Diesel',
            'code' => 'DIESEL',
            'is_active' => true,
        ]);

        $other = TripExpenseTypeMaster::query()->create([
            'name' => 'Other',
            'code' => 'OTHER',
            'is_active' => true,
        ]);

        $toll = TripExpenseTypeMaster::query()->create([
            'name' => 'Toll',
            'code' => 'TOLL',
            'is_active' => true,
        ]);

        // Default master listing excludes DIESEL and OTHER
        $indexRes = $this->actingAs($admin)
            ->getJson('/app/api/trip-expense-types')
            ->assertOk();

        $indexCodes = collect($indexRes->json('data'))->pluck('code')->all();
        $this->assertNotContains('DIESEL', $indexCodes);
        $this->assertNotContains('OTHER', $indexCodes);
        $this->assertContains('TOLL', $indexCodes);

        // Fetching all (e.g. for trip expense selection) includes them
        $allRes = $this->actingAs($admin)
            ->getJson('/app/api/trip-expense-types?all=1')
            ->assertOk();

        $allCodes = collect($allRes->json('data'))->pluck('code')->all();
        $this->assertContains('DIESEL', $allCodes);
        $this->assertContains('OTHER', $allCodes);

        // Cannot edit DIESEL or OTHER
        $this->actingAs($admin)
            ->putJson("/app/api/trip-expense-types/{$diesel->id}", [
                'name' => 'Diesel Modified',
            ])
            ->assertForbidden();

        // Cannot deactivate DIESEL or OTHER
        $this->actingAs($admin)
            ->putJson("/app/api/trip-expense-types/{$other->id}", [
                'is_active' => false,
            ])
            ->assertForbidden();

        // Cannot delete DIESEL or OTHER
        $this->actingAs($admin)
            ->deleteJson("/app/api/trip-expense-types/{$diesel->id}")
            ->assertForbidden();

        // Cannot create duplicate Diesel or Other
        $this->actingAs($admin)
            ->postJson('/app/api/trip-expense-types', [
                'name' => 'Diesel',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role, ?User $superAdmin = null): array
    {
        $superAdmin = $superAdmin ?? User::query()->create([
            'name' => 'Super Admin '.fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $tenant = Tenant::query()->create([
            'owner_user_id' => $superAdmin->id,
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
