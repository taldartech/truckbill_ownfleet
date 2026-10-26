<?php

namespace Tests\Unit\Support;

use App\Support\RoleHierarchy;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignable_roles_by_hierarchy(): void
    {
        $master = new User(['user_role_id' => UserRole::MASTER_ADMIN]);
        $this->assertSame([UserRole::SUPER_ADMIN], RoleHierarchy::assignableRoleIds($master));
        $this->assertTrue(RoleHierarchy::canAssignRole($master, UserRole::SUPER_ADMIN));
        $this->assertFalse(RoleHierarchy::canAssignRole($master, UserRole::ADMIN));

        $super = new User(['user_role_id' => UserRole::SUPER_ADMIN]);
        $this->assertSame(
            [UserRole::ADMIN, UserRole::MODERATOR, UserRole::ACCOUNTANT],
            RoleHierarchy::assignableRoleIds($super)
        );

        $admin = new User(['user_role_id' => UserRole::ADMIN]);
        $this->assertSame([], RoleHierarchy::assignableRoleIds($admin));
    }

    public function test_role_type_predicates(): void
    {
        $this->assertTrue(RoleHierarchy::isProtectedRole(UserRole::MASTER_ADMIN));
        $this->assertFalse(RoleHierarchy::isProtectedRole(UserRole::SUPER_ADMIN));

        $this->assertTrue(RoleHierarchy::isSuperAdminRole(UserRole::SUPER_ADMIN));
        $this->assertFalse(RoleHierarchy::isSuperAdminRole(UserRole::ADMIN));

        $masterUser = new User(['user_role_id' => UserRole::MASTER_ADMIN]);
        $superUser = new User(['user_role_id' => UserRole::SUPER_ADMIN]);

        $this->assertTrue(RoleHierarchy::managesSuperAdmins($masterUser));
        $this->assertFalse(RoleHierarchy::managesSuperAdmins($superUser));

        $this->assertTrue(RoleHierarchy::managesTenantUsers($superUser));
        $this->assertFalse(RoleHierarchy::managesTenantUsers($masterUser));

        $this->assertTrue(RoleHierarchy::requiresBranchAssignment(UserRole::ADMIN));
        $this->assertTrue(RoleHierarchy::requiresBranchAssignment(UserRole::MODERATOR));
        $this->assertTrue(RoleHierarchy::requiresBranchAssignment(UserRole::ACCOUNTANT));
        $this->assertFalse(RoleHierarchy::requiresBranchAssignment(UserRole::SUPER_ADMIN));
    }

    public function test_owned_tenant_ids_and_actor_owns_tenant(): void
    {
        $super = User::query()->create([
            'name' => 'Super User',
            'email' => 'super@example.com',
            'password' => bcrypt('secret123'),
            'user_role_id' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Owned Co',
            'slug' => 'owned-co',
            'timezone' => 'Asia/Kolkata',
            'owner_user_id' => $super->id,
            'is_active' => true,
        ]);

        $this->assertSame([(string) $tenant->id], RoleHierarchy::ownedTenantIds($super));
        $this->assertTrue(RoleHierarchy::actorOwnsTenant($super, (string) $tenant->id));
        $this->assertFalse(RoleHierarchy::actorOwnsTenant($super, null));
        $this->assertFalse(RoleHierarchy::actorOwnsTenant($super, ''));
        $this->assertFalse(RoleHierarchy::actorOwnsTenant($super, 'non-existent-tenant'));

        $admin = new User(['id' => 999, 'user_role_id' => UserRole::ADMIN]);
        $this->assertSame([], RoleHierarchy::ownedTenantIds($admin));
        $this->assertFalse(RoleHierarchy::actorOwnsTenant($admin, (string) $tenant->id));
    }

    public function test_can_manage_and_can_delete_user_permissions(): void
    {
        $master = new User(['id' => 1, 'user_role_id' => UserRole::MASTER_ADMIN]);
        $super1 = User::query()->create([
            'name' => 'Super 1',
            'email' => 's1@example.com',
            'password' => bcrypt('secret123'),
            'user_role_id' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
        $super2 = new User(['id' => 3, 'user_role_id' => UserRole::SUPER_ADMIN]);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1-hier',
            'timezone' => 'Asia/Kolkata',
            'owner_user_id' => $super1->id,
            'is_active' => true,
        ]);

        $tenantAdmin = new User([
            'id' => 4,
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
        ]);

        $unrelatedAdmin = new User([
            'id' => 5,
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => 'different-tenant-id',
        ]);

        // Master Admin manages Super Admin
        $this->assertTrue(RoleHierarchy::canManageUser($master, $super1));
        $this->assertFalse(RoleHierarchy::canManageUser($master, $master));

        // Cannot delete self
        $this->assertFalse(RoleHierarchy::canDeleteUser($master, $master));

        // Super Admin cannot manage another Super Admin
        $this->assertFalse(RoleHierarchy::canManageUser($super1, $super2));

        // Super Admin manages users in owned tenant
        $this->assertTrue(RoleHierarchy::canManageUser($super1, $tenantAdmin));
        $this->assertTrue(RoleHierarchy::canDeleteUser($super1, $tenantAdmin));

        // Super Admin cannot manage users in unowned tenant
        $this->assertFalse(RoleHierarchy::canManageUser($super1, $unrelatedAdmin));

        // Admin cannot manage anyone
        $this->assertFalse(RoleHierarchy::canManageUser($tenantAdmin, $unrelatedAdmin));
    }

    public function test_can_modify_role_permissions_and_labels(): void
    {
        $master = new User(['id' => 1, 'user_role_id' => UserRole::MASTER_ADMIN]);
        $admin = new User(['id' => 2, 'user_role_id' => UserRole::ADMIN]);

        $masterRole = new UserRole();
        $masterRole->id = UserRole::MASTER_ADMIN;

        $superRole = new UserRole();
        $superRole->id = UserRole::SUPER_ADMIN;

        $this->assertTrue(RoleHierarchy::canModifyRolePermissions($master, $masterRole));
        $this->assertTrue(RoleHierarchy::canModifyRolePermissions($master, $superRole));
        $this->assertFalse(RoleHierarchy::canModifyRolePermissions($admin, $masterRole));
        $this->assertFalse(RoleHierarchy::canModifyRolePermissions($admin, $superRole));

        $this->assertSame('Master Admin', RoleHierarchy::roleLabel(UserRole::MASTER_ADMIN));
        $this->assertSame('Super Admin', RoleHierarchy::roleLabel(UserRole::SUPER_ADMIN));
        $this->assertSame('Admin', RoleHierarchy::roleLabel(UserRole::ADMIN));
        $this->assertSame('Moderator', RoleHierarchy::roleLabel(UserRole::MODERATOR));
        $this->assertSame('Accountant', RoleHierarchy::roleLabel(UserRole::ACCOUNTANT));
        $this->assertSame('Unknown', RoleHierarchy::roleLabel(999));
    }
}
