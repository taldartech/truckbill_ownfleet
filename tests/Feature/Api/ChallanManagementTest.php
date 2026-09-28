<?php

namespace Tests\Feature\Api;

use App\Challan;
use App\Driver;
use App\Permission;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChallanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_no_role_has_challan_permissions_by_default(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');

        $this->actingAs($user)
            ->getJson('/app/api/challans')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/app/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-DENY-1',
                'challan_date' => '2026-06-01',
                'violation_type' => 'Over speeding',
                'amount' => 1500,
                'status' => 'UNPAID',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_and_list_challan_when_permissions_are_granted(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        $this->grantChallanPermissions('admin');

        $this->actingAs($user)
            ->postJson('/app/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-1001',
                'challan_date' => '2026-06-01',
                'violation_type' => 'Over speeding',
                'amount' => 1500,
                'status' => 'UNPAID',
            ])
            ->assertCreated()
            ->assertJsonPath('data.challan_number', 'CH-1001')
            ->assertJsonPath('data.status', 'UNPAID');

        $this->actingAs($user)
            ->getJson('/app/api/challans?search=CH-1001')
            ->assertOk()
            ->assertJsonPath('data.0.challan_number', 'CH-1001')
            ->assertJsonPath('data.0.vehicle.registration_number', $vehicle->registration_number)
            ->assertJsonPath('data.0.driver.name', $driver->name);

        $this->assertDatabaseHas('challans', [
            'tenant_id' => $tenant->id,
            'challan_number' => 'CH-1001',
            'violation_type' => 'Over speeding',
        ]);
    }

    public function test_admin_cannot_delete_challan_without_delete_permission(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        $this->grantChallanPermissions('admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-DEL-1',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user)
            ->deleteJson("/app/api/challans/{$challan->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('challans', ['id' => $challan->id]);
    }

    public function test_super_admin_can_delete_challan_when_delete_permission_is_granted(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('super_admin');
        $this->grantChallanPermissions('super_admin', withDelete: true);

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-DEL-2',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, \App\Branch::query()->findOrFail($vehicle->branch_id));
        $this->withHeader('X-Tenant-Id', $tenant->id);

        $this->actingAs($user)
            ->deleteJson("/app/api/challans/{$challan->id}")
            ->assertNoContent();

        $this->assertNull(Challan::query()->find($challan->id));
        $this->assertNotNull(Challan::query()->withTrashed()->find($challan->id));
        $this->assertSoftDeleted('challans', ['id' => $challan->id]);
    }

    public function test_super_admin_can_recreate_challan_number_after_soft_delete(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('super_admin');
        $this->grantChallanPermissions('super_admin', withDelete: true);

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-REUSE-1',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, \App\Branch::query()->findOrFail($vehicle->branch_id));
        $this->withHeader('X-Tenant-Id', $tenant->id);

        $this->actingAs($user)
            ->deleteJson("/app/api/challans/{$challan->id}")
            ->assertNoContent();

        $this->actingAs($user)
            ->postJson('/app/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-REUSE-1',
                'challan_date' => '2026-06-01',
                'violation_type' => 'Over speeding',
                'amount' => 750,
                'status' => 'UNPAID',
            ])
            ->assertCreated()
            ->assertJsonPath('data.challan_number', 'CH-REUSE-1');
    }

    public function test_challan_edit_payload_keeps_soft_deleted_driver_name_and_allows_update(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        $this->grantChallanPermissions('admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-SOFT-DRIVER',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $driver->update(['is_active' => false, 'deleted_by' => $user->id]);
        $driver->delete();

        $this->actingAs($user)
            ->getJson("/app/api/challans/{$challan->id}")
            ->assertOk()
            ->assertJsonPath('data.driver.name', $driver->name);

        $this->actingAs($user)
            ->putJson("/app/api/challans/{$challan->id}", [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-SOFT-DRIVER',
                'challan_date' => '2026-05-15',
                'violation_type' => 'Signal jump',
                'amount' => 600,
                'status' => 'PAID',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '600.00')
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.driver.name', $driver->name);
    }

    public function test_moderator_can_view_and_create_challans(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('moderator');

        $this->actingAs($user)
            ->getJson('/app/api/challans')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->postJson('/app/api/challans', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-MOD-1',
                'challan_date' => '2026-06-01',
                'violation_type' => 'Over speeding',
                'amount' => 1500,
                'status' => 'UNPAID',
            ])
            ->assertCreated()
            ->assertJsonPath('data.challan_number', 'CH-MOD-1');

        $this->assertDatabaseHas('challans', [
            'tenant_id' => $tenant->id,
            'challan_number' => 'CH-MOD-1',
        ]);
    }

    public function test_admin_can_update_challan_payment_status_when_permissions_are_granted(): void
    {
        [$tenant, $user, $vehicle, $driver] = $this->createFixtures('admin');
        $this->grantChallanPermissions('admin');

        $challan = Challan::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'challan_number' => 'CH-2002',
            'challan_date' => '2026-05-15',
            'violation_type' => 'Signal jump',
            'amount' => 500,
            'status' => 'UNPAID',
        ]);

        $this->actingAs($user)
            ->putJson("/app/api/challans/{$challan->id}", [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'challan_number' => 'CH-2002',
                'challan_date' => '2026-05-15',
                'violation_type' => 'Signal jump',
                'amount' => 500,
                'status' => 'PAID',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Challan Tenant',
            'slug' => 'challan-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);
        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        $branchId = (int) $operationalAttributes['branch_id'];
        \App\Support\SelectedCompanySession::set((string) $tenant->id);
        \App\Support\SelectedBranchSession::set($branchId);
        \App\Support\TenantContext::setTenantId((string) $tenant->id);
        \App\Support\BranchContext::setBranchId($branchId);

        $user = User::query()->create([
            ...$operationalAttributes,
            'name' => 'Challan User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role),
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operationalAttributes,
            'registration_number' => 'MH12AB1234',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operationalAttributes,
            'name' => 'Test Driver',
            'phone' => '9876543210',
            'license_number' => 'DL-TEST',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => 1000,
            'is_active' => true,
        ]);

        return [$tenant, $user, $vehicle, $driver];
    }

    private function grantChallanPermissions(string $role, bool $withDelete = false): void
    {
        $definitions = [
            'challans.view' => 'View challans',
            'challans.manage' => 'Create & edit challans',
        ];

        if ($withDelete) {
            $definitions['challans.delete'] = 'Delete challans';
        }

        foreach ($definitions as $slug => $name) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'compliance'],
            );

            DB::table('permission_user_role')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_role_id' => UserRole::idFor($role),
            ]);
        }
    }
}
