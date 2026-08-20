<?php

namespace Tests\Feature;

use App\Permission;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_has_driver_manage_permission_via_gate(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-perms',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $user->load('userRole.permissions');

        $this->assertTrue($user->hasPermission('drivers.manage'));
        $this->assertTrue(Gate::forUser($user)->allows('drivers.manage'));
        $this->assertTrue(Gate::forUser($user)->allows('create', \App\Driver::class));
    }

    public function test_moderator_has_create_and_view_permissions_without_full_manage(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Moderator Tenant',
            'slug' => 'moderator-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Moderator',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MODERATOR,
        ]);

        $user->load('userRole.permissions');

        $this->assertTrue($user->hasPermission('drivers.view'));
        $this->assertTrue($user->hasPermission('drivers.create'));
        $this->assertTrue($user->hasPermission('drivers.manage'));
        $this->assertTrue($user->hasPermission('vehicles.create'));
        $this->assertFalse($user->hasPermission('vehicles.edit'));
        $this->assertTrue($user->hasPermission('trips.create'));
        $this->assertTrue($user->hasPermission('attendances.create'));
        $this->assertTrue($user->hasPermission('attendance.bulk_mark'));
        $this->assertTrue($user->hasPermission('stations.create'));
        $this->assertTrue($user->hasPermission('brokers.create'));
        $this->assertTrue($user->hasPermission('customers.create'));
        $this->assertTrue($user->hasPermission('tyre_layouts.create'));
        $this->assertFalse($user->hasPermission('locations.create'));
        $this->assertFalse($user->hasPermission('reports.export'));
        $this->assertFalse($user->hasPermission('attendance_reports.export'));
        $this->assertFalse($user->hasPermission('vehicles.delete'));
        $this->assertFalse($user->hasPermission('maintenance.edit'));
        $this->assertTrue($user->hasPermission('vehicle_documents.create'));
        $this->assertTrue($user->hasPermission('vehicle_documents.edit'));
        $this->assertFalse($user->hasPermission('trips.archive'));
        $this->assertFalse($user->hasPermission('trips.view_archived'));
        $this->assertFalse($user->hasPermission('trips.restore'));
        $this->assertFalse($user->hasPermission('trips.edit'));
        $this->assertTrue($user->hasPermission('trips.expenses'));
        $this->assertFalse($user->hasPermission('wages.view'));
        $this->assertTrue($user->hasPermission('fleet_monthly_report.view'));
        $this->assertTrue(Gate::forUser($user)->allows('create', \App\Driver::class));
        $this->assertFalse($user->canUpdateOperationalRecords());
        $this->assertFalse($user->canDeleteOperationalRecords());
    }

    public function test_super_admin_receives_operational_permissions_but_not_master_admin_only(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Super Tenant',
            'slug' => 'super-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $user->load('userRole.permissions');

        $this->assertSame(
            count(\App\Support\PermissionCatalog::superAdminSlugs()),
            count($user->permissionSlugs()),
        );

        foreach (\App\Support\PermissionCatalog::masterAdminOnlySlugs() as $slug) {
            $this->assertFalse($user->hasPermission($slug), "Super admin must not have [{$slug}].");
        }

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertTrue($user->hasPermission('branches.view'));
        $this->assertTrue(Gate::forUser($user)->denies('viewAny', \App\UserRole::class));
        $this->assertTrue(Gate::forUser($user)->denies('viewAny', \App\Permission::class));
    }

    public function test_effective_tenant_id_is_cached_per_user_instance(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cache Tenant',
            'slug' => 'cache-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cached User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $first = $user->effectiveTenantId();
        $second = $user->effectiveTenantId();

        $this->assertSame($tenant->id, $first);
        $this->assertSame($first, $second);
    }

    public function test_me_endpoint_includes_permissions(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Me Tenant',
            'slug' => 'me-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Me User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $user->load('userRole.permissions');

        $response = $this->actingAs($user)->getJson('/app/user');

        $response->assertOk();
        $response->assertJsonStructure(['user' => ['permissions']]);
        $response->assertJsonFragment(['permissions' => $user->fresh()->permissionSlugs()]);
    }

    public function test_permission_seeder_creates_expected_slugs(): void
    {
        $this->assertGreaterThanOrEqual(80, Permission::query()->count());
        $this->assertDatabaseHas('permissions', ['slug' => 'drivers.manage']);
        $this->assertDatabaseHas('permissions', ['slug' => 'drivers.create']);
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('permission_user_role')->count());
    }
}
