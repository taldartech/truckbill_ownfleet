<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Permission;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

    public function test_user_role_id_for_known_and_unknown_names(): void
    {
        $this->assertSame(UserRole::MASTER_ADMIN, UserRole::idFor('master_admin'));
        $this->assertSame(UserRole::SUPER_ADMIN, UserRole::idFor('super-admin'));
        $this->assertSame(UserRole::ADMIN, UserRole::idFor('ADMIN'));
        $this->assertSame(UserRole::MODERATOR, UserRole::idFor('operator'));
        $this->assertNull(UserRole::idFor('nonexistent'));
    }

    public function test_user_role_system_role_ids_returns_all_defined_ids(): void
    {
        $ids = UserRole::systemRoleIds();
        $expected = [
            UserRole::MASTER_ADMIN,
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN,
            UserRole::MODERATOR,
            UserRole::ACCOUNTANT,
        ];
        $this->assertEqualsCanonicalizing($expected, $ids);
    }

    public function test_user_role_scope_system_roles_applies_correct_where_clause(): void
    {
        $query = UserRole::query()->systemRoles();
        $sql = $query->toSql();
        $this->assertStringContainsString('where "id" in (', $sql);
        $bindings = $query->getBindings();
        foreach (UserRole::systemRoleIds() as $id) {
            $this->assertContains($id, $bindings);
        }
    }

    public function test_user_role_relations(): void
    {
        $role = new UserRole();
        $this->assertInstanceOf(HasMany::class, $role->users());
        $this->assertInstanceOf(BelongsToMany::class, $role->permissions());
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = new User();
        $user->setRelation('userRole', new UserRole(['name' => $roleName]));

        return $user;
    }

    public function test_user_can_act_as_admin_returns_true_for_allowed_roles(): void
    {
        $master = $this->makeUserWithRole('master_admin');
        $this->assertTrue($master->canActAsAdmin());

        $super = $this->makeUserWithRole('super_admin');
        $this->assertTrue($super->canActAsAdmin());

        $admin = $this->makeUserWithRole('admin');
        $this->assertTrue($admin->canActAsAdmin());

        $mod = $this->makeUserWithRole('moderator');
        $this->assertTrue($mod->canActAsAdmin());

        $acct = $this->makeUserWithRole('accountant');
        $this->assertFalse($acct->canActAsAdmin());
    }

    public function test_user_send_password_reset_notification_sends_mail_with_correct_token(): void
    {
        Mail::fake();
        $user = new User(['email' => 'user@example.com']);
        $token = 'sample-token-123';
        $user->sendPasswordResetNotification($token);
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user, $token): bool {
            return $mail->email === $user->email && $mail->token === $token;
        });
    }

    public function test_user_matches_role_compares_normalized_names(): void
    {
        $user = $this->makeUserWithRole('admin');
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertFalse($user->hasRole('moderator'));

        $userWithoutRole = new User();
        $this->assertFalse($userWithoutRole->hasRole('admin'));
    }

    public function test_user_normalize_role_name_handles_hyphens_and_operator(): void
    {
        $ref = new \ReflectionMethod(User::class, 'normalizeRoleName');
        $this->assertSame('master_admin', $ref->invoke(null, 'master-admin'));
        $this->assertSame('moderator', $ref->invoke(null, 'operator'));
        $this->assertSame('super_admin', $ref->invoke(null, 'Super-Admin'));
    }

    public function test_user_dashboard_url_returns_expected_path(): void
    {
        $user = new User();
        $this->assertSame('/app/dashboard', $user->dashboardUrl());
    }

    public function test_user_resolved_super_admin_id_handles_various_contexts_and_database_fallbacks(): void
    {
        // 1. Direct super admin user role
        $super = new User(['user_role_id' => UserRole::SUPER_ADMIN]);
        $super->id = 'user-1';
        $this->assertSame('user-1', $super->resolvedSuperAdminId());

        // 2. Direct super_admin role string
        $userWithRole = $this->makeUserWithRole('super_admin');
        $userWithRole->id = 'user-2';
        $this->assertSame('user-2', $userWithRole->resolvedSuperAdminId());

        // 3. User with no role or tenant
        $plain = new User();
        $this->assertNull($plain->resolvedSuperAdminId());

        // 4. User resolved via tenant owner in database
        $owner = User::query()->create([
            'name' => 'Owner Admin',
            'email' => 'owner@example.com',
            'password' => 'secret',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Resolved Super Admin Tenant',
            'slug' => 'resolved-super-admin-tenant',
            'status' => 'active',
            'owner_user_id' => $owner->id,
        ]);

        $user = new User([
            'name' => 'Sub User',
            'email' => 'sub@example.com',
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertSame((string) $owner->id, $user->resolvedSuperAdminId());

        // 5. Fallback direct query on Tenant when resolvedCompany() lacks owner_user_id (lines 121-126)
        $userFallback = new User([
            'name' => 'Sub User Fallback',
            'email' => 'fallback@example.com',
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
        ]);
        $userFallback->setRelation('tenant', new Tenant(['name' => 'Partial Tenant', 'owner_user_id' => null]));
        $this->assertSame((string) $owner->id, $userFallback->resolvedSuperAdminId());

        // 6. Tenant in DB has no owner_user_id
        $tenantNoOwner = Tenant::query()->create([
            'name' => 'No Owner Tenant',
            'slug' => 'no-owner-tenant',
            'status' => 'active',
            'owner_user_id' => null,
        ]);
        $userNoOwner = new User([
            'name' => 'Sub User No Owner',
            'email' => 'noowner@example.com',
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => $tenantNoOwner->id,
        ]);
        $this->assertNull($userNoOwner->resolvedSuperAdminId());

        // 7. Non-existent tenant_id in DB
        $userNonExistent = new User([
            'name' => 'Ghost Tenant User',
            'email' => 'ghost@example.com',
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => '00000000-0000-0000-0000-000000000000',
        ]);
        $this->assertNull($userNonExistent->resolvedSuperAdminId());

        // 8. Tenant has no owner, but user themselves has super_admin role via hasRole
        $userSuperAdminRole = new User([
            'name' => 'Super Admin Role User',
            'email' => 'superrole@example.com',
            'tenant_id' => $tenantNoOwner->id,
        ]);
        $userSuperAdminRole->id = 'super-role-id';
        $userSuperAdminRole->setRelation('userRole', new UserRole(['name' => 'super_admin']));
        $this->assertSame('super-role-id', $userSuperAdminRole->resolvedSuperAdminId());
    }

    public function test_user_role_relation_returns_belongs_to(): void
    {
        $user = new User();
        $this->assertInstanceOf(BelongsTo::class, $user->userRole());
    }

    public function test_user_can_delete_and_update_operational_records(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $this->assertTrue($admin->canDeleteOperationalRecords());
        $this->assertTrue($admin->canUpdateOperationalRecords());

        $accountant = $this->makeUserWithRole('accountant');
        $this->assertFalse($accountant->canDeleteOperationalRecords());
        $this->assertFalse($accountant->canUpdateOperationalRecords());

        $moderator = $this->makeUserWithRole('moderator');
        $this->assertFalse($moderator->canDeleteOperationalRecords());
        $this->assertFalse($moderator->canUpdateOperationalRecords());
    }
}
