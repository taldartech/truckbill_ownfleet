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

    public function test_moderator_cannot_manage_drivers(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acct Tenant',
            'slug' => 'acct-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Accountant',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MODERATOR,
        ]);

        $user->load('userRole.permissions');

        $this->assertFalse($user->hasPermission('drivers.view'));
        $this->assertFalse($user->hasPermission('drivers.manage'));
        $this->assertTrue($user->hasPermission('stations.view'));
        $this->assertTrue($user->hasPermission('stations.manage'));
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
        $this->assertGreaterThanOrEqual(8, Permission::query()->count());
        $this->assertDatabaseHas('permissions', ['slug' => 'drivers.manage']);
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('permission_user_role')->count());
    }
}
