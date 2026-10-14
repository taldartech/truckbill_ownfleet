<?php

namespace Tests;

use App\Branch;
use App\BranchSetting;
use App\Support\BranchContext;
use App\Support\BranchProvisioner;
use App\Support\PayrollPayDays;
use App\Support\RoleHierarchy;
use App\Support\TenantContext;
use App\Tenant;
use App\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed([
            \Database\Seeders\UserRoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\IndiaLocationSeeder::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setTenantId(null);
        BranchContext::setBranchId(null);
        $this->flushSession();
    }

    protected function tearDown(): void
    {
        TenantContext::setTenantId(null);
        BranchContext::setBranchId(null);
        PayrollPayDays::clearCache();

        parent::tearDown();
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        $result = parent::actingAs($user, $guard);

        if ($user instanceof User) {
            $this->applyUserOperationalContext($user);
        }

        return $result;
    }

    protected function applyUserOperationalContext(User $user): void
    {
        $tenantId = $user->effectiveTenantId();

        if ($tenantId !== null) {
            TenantContext::setTenantId($tenantId);
        }

        $branchId = $user->effectiveBranchId();

        if ($branchId !== null) {
            BranchContext::setBranchId($branchId);

            return;
        }

        if ($user->tenant_id !== null) {
            $tenant = $user->tenant ?? Tenant::query()->find($user->tenant_id);
            if ($tenant && ! \App\Models\TenantSubscription::query()->where('tenant_id', $tenant->id)->exists()) {
                $this->provisionActiveSubscription($tenant);
            }
        }

        if (RoleHierarchy::requiresBranchAssignment((int) $user->user_role_id) && $user->tenant_id !== null) {
            $tenant = $user->tenant ?? Tenant::query()->find($user->tenant_id);
            $branch = BranchProvisioner::ensureDefaultBranch($tenant);
            $user->forceFill(['branch_id' => $branch->id])->save();
            BranchContext::setBranchId((int) $branch->id);
        }
    }

    protected function createBranchForTenant(Tenant $tenant, array $attributes = []): Branch
    {
        $settingsAttributes = $attributes['number_settings'] ?? [];
        unset($attributes['number_settings']);

        $branch = Branch::query()->create(array_merge([
            'company_id' => $tenant->id,
            'branch_name' => $tenant->name,
            'branch_address' => 'Test Address',
            'branch_contact_number' => fake()->unique()->numerify('9#########'),
            'head_office' => true,
        ], $attributes));

        if ($branch->settings()->doesntExist()) {
            BranchSetting::createForBranch($branch->id, is_array($settingsAttributes) ? $settingsAttributes : []);
        }

        return $branch->fresh(['settings']);
    }

    protected function assignBranchIfRequired(User $user, ?Tenant $tenant = null): User
    {
        $tenant ??= $user->tenant ?? Tenant::query()->find($user->tenant_id);

        if ($tenant && ! \App\Models\TenantSubscription::query()->where('tenant_id', $tenant->id)->exists()) {
            $this->provisionActiveSubscription($tenant);
        }

        if (! RoleHierarchy::requiresBranchAssignment((int) $user->user_role_id)) {
            return $user;
        }

        if ($user->branch_id !== null) {
            BranchContext::setBranchId((int) $user->branch_id);

            return $user;
        }

        $branch = BranchProvisioner::ensureDefaultBranch($tenant);
        $user->update(['branch_id' => $branch->id]);
        BranchContext::setBranchId((int) $branch->id);

        return $user->fresh();
    }

    /**
     * @return array{tenant_id: string, branch_id: int}
     */
    protected function operationalAttributesForTenant(Tenant $tenant): array
    {
        $branch = BranchProvisioner::ensureDefaultBranch($tenant);

        if (! \App\Models\TenantSubscription::query()->where('tenant_id', $tenant->id)->exists()) {
            $this->provisionActiveSubscription($tenant);
        }

        return [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
        ];
    }

    protected function enterBranchForSuperAdmin(User $user, Branch $branch): void
    {
        \App\Support\SelectedCompanySession::set((string) $branch->company_id);
        \App\Support\SelectedBranchSession::set((int) $branch->id);
        TenantContext::setTenantId((string) $branch->company_id);
        BranchContext::setBranchId((int) $branch->id);
    }

    protected function formatTripNumber(int $branchId, int $sequence = 1, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $sequencePart = str_pad((string) $sequence, max(4, strlen((string) $sequence)), '0', STR_PAD_LEFT);

        return sprintf('TRIP-BR%03d-%d-%s', $branchId, $year, $sequencePart);
    }

    protected function provisionActiveSubscription(Tenant $tenant, array $attributes = []): \App\Models\TenantSubscription
    {
        return \App\Models\TenantSubscription::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'included_branches' => 5,
            'users_per_branch' => 10,
            'included_trucks' => 50,
        ], $attributes));
    }
}
