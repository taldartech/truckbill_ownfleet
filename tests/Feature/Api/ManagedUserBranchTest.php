<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedUserBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_company_user_requires_branch_on_create(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Branch User Co');
        $branch = $this->createBranch($tenant, 'Branch User Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/managed-users', [
                'name' => 'No Branch User',
                'email' => 'nobranch@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::ADMIN,
                'tenant_id' => $tenant->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/managed-users', [
                'name' => 'Branch User',
                'email' => 'branchuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::MODERATOR,
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.branch_name', 'Branch User Co');
    }

    public function test_branch_must_belong_to_selected_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenantA = $this->createOwnedTenant($superAdmin, 'Company Alpha', 'CALPHA');
        $tenantB = $this->createOwnedTenant($superAdmin, 'Company Beta', 'CBETA');
        $branchB = $this->createBranch($tenantB, 'Company B');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/managed-users', [
                'name' => 'Wrong Branch User',
                'email' => 'wrongbranch@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::ACCOUNTANT,
                'tenant_id' => $tenantA->id,
                'branch_id' => $branchB->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_user_can_be_updated_without_resubmitting_branch(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Update Co');
        $branch = $this->createBranch($tenant, 'Update Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/managed-users', [
                'name' => 'Alert User',
                'email' => 'alertuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_role_id' => UserRole::ACCOUNTANT,
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ])
            ->assertCreated();

        $userId = User::query()->where('email', 'alertuser@example.com')->value('id');

        $this->actingAs($superAdmin)
            ->putJson('/app/api/managed-users/'.$userId, [
                'document_alerts_live' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_id', $branch->id);
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createOwnedTenant(User $owner, string $name, ?string $shortName = null): Tenant
    {
        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'short_name' => $shortName ?? str($name)->upper()->substr(0, 8)->toString(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);

        return $tenant;
    }

    private function createBranch(Tenant $tenant, string $name): Branch
    {
        return Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => $name,
            'branch_contact_number' => fake()->unique()->numerify('98########'),
            'head_office' => true,
        ]);
    }
}
