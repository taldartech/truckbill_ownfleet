<?php

namespace App\Services;

use App\Branch;
use App\Models\TenantSubscription;
use App\Support\SubscriptionExpiredResponse;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Validation\ValidationException;

class SubscriptionGuard
{
    public function __construct(
        private readonly SubscriptionUsageService $usageService,
    ) {}

    public function assertCanMutate(Tenant $tenant): void
    {
        if (! $tenant->subscriptionCanWrite()) {
            SubscriptionExpiredResponse::abortUnlessCanMutate(request(), $tenant);
        }
    }

    public function assertPlanActive(?TenantSubscription $subscription): void
    {
        if ($subscription === null) {
            SubscriptionExpiredResponse::abortUnlessCanMutate(request());
        }

        $this->assertCanMutate($subscription->tenant);
    }

    public function assertCanCreateBranch(Tenant $tenant): void
    {
        $subscription = $tenant->subscription;
        $this->assertCanMutate($tenant);

        $count = Branch::query()->where('company_id', $tenant->id)->count();
        $max = $subscription->maxBranches();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'company_id' => "Branch limit reached ({$count}/{$max}). Purchase a branch add-on from Subscription → Add-ons.",
            ]);
        }
    }

    public function assertCanCreateUser(Tenant $tenant): void
    {
        $subscription = $tenant->subscription;
        $this->assertCanMutate($tenant);

        $totalUsers = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->count();

        if ($totalUsers >= $subscription->maxCompanyUsers()) {
            abort(422, 'Company user limit reached.');
        }
    }

    public function assertCanAssignUserToBranch(Tenant $tenant, string $branchId, ?string $excludingUserId = null): void
    {
        $subscription = $tenant->subscription;
        $this->assertCanMutate($tenant);

        $perBranch = $subscription->maxUsersInBranch();
        $addonUsers = (int) $subscription->addon_users;
        $totalUsers = $this->countCompanyUsers($tenant, $excludingUserId);
        $usersInBranch = $this->countUsersInBranch($tenant, $branchId, $excludingUserId);
        $effectiveMax = $this->effectiveUserCapacity($tenant, $subscription);

        if ($totalUsers >= $effectiveMax) {
            throw ValidationException::withMessages([
                'branch_id' => 'Company user limit reached for your subscription.',
            ]);
        }

        if ($usersInBranch < $perBranch) {
            return;
        }

        $overflowUsed = $this->usageService->overflowUsersUsed($tenant, $perBranch, $excludingUserId);

        if ($overflowUsed >= $addonUsers) {
            throw ValidationException::withMessages([
                'branch_id' => "This branch already has the maximum number of users ({$perBranch} included"
                    .($addonUsers > 0 ? " + {$addonUsers} add-on" : '')
                    .'). Assign the user to another branch or purchase more user add-ons.',
            ]);
        }
    }

    public function assertCanCreateVehicle(Tenant $tenant): void
    {
        $subscription = $tenant->subscription;
        $this->assertCanMutate($tenant);

        $count = $this->usageService->vehicleCountForTenant($tenant);
        $max = $subscription->maxTrucks();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'registration_number' => "Vehicle limit reached ({$count}/{$max}). "
                    .'Purchase a vehicle add-on from Subscription → Add-ons.',
            ]);
        }
    }

    private function effectiveUserCapacity(Tenant $tenant, TenantSubscription $subscription): int
    {
        $branchCount = Branch::query()->where('company_id', $tenant->id)->count();
        $perBranch = $subscription->maxUsersInBranch();

        return min(
            $subscription->maxCompanyUsers(),
            ($branchCount * $perBranch) + (int) $subscription->addon_users,
        );
    }

    private function countCompanyUsers(Tenant $tenant, ?string $excludingUserId = null): int
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->when($excludingUserId !== null, fn ($query) => $query->where('id', '!=', $excludingUserId))
            ->count();
    }

    private function countUsersInBranch(Tenant $tenant, string $branchId, ?string $excludingUserId = null): int
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branchId)
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->when($excludingUserId !== null, fn ($query) => $query->where('id', '!=', $excludingUserId))
            ->count();
    }
}
