<?php

namespace Tests\Feature\Api;

use App\Permission;
use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_master_admin_can_list_roles_and_sync_permissions(): void
    {
        $master = $this->createUserWithRole(UserRole::MASTER_ADMIN);

        $response = $this->actingAs($master)->getJson('/app/api/user-roles');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'admin']);
        $response->assertJsonFragment(['name' => 'accountant']);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonMissing(['name' => 'transporter']);
        $response->assertJsonMissing(['name' => 'customer']);

        $this->actingAs($master)
            ->putJson('/app/api/user-roles/99999/permissions', [
                'permission_ids' => [],
            ])
            ->assertNotFound();
    }

    public function test_master_admin_can_sync_admin_role_permissions(): void
    {
        $master = $this->createUserWithRole(UserRole::MASTER_ADMIN);
        $permission = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();

        $response = $this->actingAs($master)->putJson('/app/api/user-roles/'.UserRole::ADMIN.'/permissions', [
            'permission_ids' => [$permission->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('permission_user_role', [
            'user_role_id' => UserRole::ADMIN,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_super_admin_cannot_access_roles_api(): void
    {
        $super = $this->createUserWithRole(UserRole::SUPER_ADMIN);

        $this->actingAs($super)
            ->getJson('/app/api/user-roles')
            ->assertForbidden();
    }

    public function test_super_admin_cannot_create_super_admin_user(): void
    {
        $super = $this->createUserWithRole(UserRole::SUPER_ADMIN);

        $response = $this->actingAs($super)->postJson('/app/api/managed-users', [
            'name' => 'Another Super',
            'email' => 'super2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_role_id']);
    }

    public function test_master_admin_can_create_super_admin_user(): void
    {
        $master = $this->createUserWithRole(UserRole::MASTER_ADMIN);

        $response = $this->actingAs($master)->postJson('/app/api/managed-users', [
            'name' => 'New Super Admin',
            'email' => 'newsuper@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'newsuper@example.com',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    public function test_master_admin_user_cannot_be_deleted(): void
    {
        $master = $this->createUserWithRole(UserRole::MASTER_ADMIN);
        $target = User::query()->create([
            'name' => 'Protected Master',
            'email' => 'protected-master@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::MASTER_ADMIN,
        ]);

        $response = $this->actingAs($master)->deleteJson("/app/api/managed-users/{$target->id}");

        $response->assertNotFound();
    }

    public function test_moderator_cannot_access_role_api(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'RBAC Tenant',
            'slug' => 'rbac-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Accountant',
            'email' => 'acct-rbac@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::MODERATOR,
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/user-roles')
            ->assertForbidden();
    }

    public function test_super_admin_can_manage_users_in_owned_tenant_only(): void
    {
        $super = $this->createUserWithRole(UserRole::SUPER_ADMIN);

        $ownedTenant = Tenant::query()->create([
            'owner_user_id' => $super->id,
            'name' => 'Owned Co',
            'slug' => 'owned-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $ownedBranch = \App\Branch::query()->create([
            'company_id' => $ownedTenant->id,
            'branch_name' => 'Owned Co',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
        ]);

        $otherSuper = User::query()->create([
            'name' => 'Other Super',
            'email' => 'other-super@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $foreignTenant = Tenant::query()->create([
            'owner_user_id' => $otherSuper->id,
            'name' => 'Foreign Co',
            'slug' => 'foreign-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->actingAs($super)
            ->postJson('/app/api/managed-users', [
                'name' => 'Tenant Admin',
                'email' => 'tenant-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::ADMIN,
                'tenant_id' => $ownedTenant->id,
                'branch_id' => $ownedBranch->id,
            ])
            ->assertCreated();

        $this->actingAs($super)
            ->postJson('/app/api/managed-users', [
                'name' => 'Foreign Admin',
                'email' => 'foreign-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::ADMIN,
                'tenant_id' => $foreignTenant->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id']);

        SelectedCompanySession::set($ownedTenant->id);

        $this->actingAs($super)
            ->getJson('/app/api/managed-users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'tenant-admin@example.com'])
            ->assertJsonMissing(['email' => 'foreign-admin@example.com']);
    }

    public function test_permission_crud_requires_manage_permission(): void
    {
        $master = $this->createUserWithRole(UserRole::MASTER_ADMIN);

        $this->actingAs($master)
            ->postJson('/app/api/permissions', [
                'slug' => 'custom.action',
                'name' => 'Custom Action',
                'group' => 'custom',
            ])
            ->assertCreated();

        $created = Permission::query()->where('slug', 'custom.action')->firstOrFail();

        $this->actingAs($master)
            ->putJson("/app/api/permissions/{$created->id}", [
                'name' => 'Custom Action Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Custom Action Updated');

        $this->actingAs($master)
            ->deleteJson("/app/api/permissions/{$created->id}")
            ->assertNoContent();
    }

    private function createUserWithRole(int $roleId): User
    {
        return User::query()->create([
            'name' => 'RBAC Actor',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => $roleId,
            'tenant_id' => $roleId === UserRole::SUPER_ADMIN ? null : null,
        ]);
    }
}
