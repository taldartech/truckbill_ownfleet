<?php

namespace App\Services;

use App\Branch;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;

class SubscriptionUsageService
{
    public function usageForTenant(Tenant $tenant): array
    {
        $branchCount = Branch::query()
            ->where('company_id', $tenant->id)
            ->count();

        $usersPerBranch = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('branch_id')
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->selectRaw('branch_id, count(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt');

        $totalUsers = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->count();

        return [
            'branches' => $branchCount,
            'max_users_in_any_branch' => (int) ($usersPerBranch->max() ?? 0),
            'total_users' => $totalUsers,
            'trucks' => $this->vehicleCountForTenant($tenant),
            'lr_credits_remaining' => $tenant->subscription?->lr_credits_remaining ?? 0,
        ];
    }

    public function overflowUsersUsed(Tenant $tenant, int $perBranch, ?string $excludingUserId = null): int
    {
        return (int) User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('branch_id')
            ->whereNotIn('user_role_id', [UserRole::SUPER_ADMIN, UserRole::MASTER_ADMIN])
            ->when($excludingUserId !== null, fn ($query) => $query->where('id', '!=', $excludingUserId))
            ->selectRaw('branch_id, count(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt')
            ->sum(fn ($count) => max(0, (int) $count - $perBranch));
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array{addon_branches: int, addon_users: int, addon_trucks: int}
     */
    public function minimumRenewalAddons(Tenant $tenant, array $catalog): array
    {
        $usage = $this->usageForTenant($tenant);
        $perBranch = (int) $catalog['users_per_branch'];
        $includedBranches = (int) $catalog['included_branches'];
        $includedTrucks = (int) ($catalog['included_trucks'] ?? 0);

        $minBranches = max(0, $usage['branches'] - $includedBranches);
        $branchSlots = $includedBranches + $minBranches;
        $minUsersForTotal = max(0, $usage['total_users'] - ($branchSlots * $perBranch));
        $minUsersForOverflow = $this->overflowUsersUsed($tenant, $perBranch);

        return [
            'addon_branches' => $minBranches,
            'addon_users' => max($minUsersForTotal, $minUsersForOverflow),
            'addon_trucks' => max(0, $usage['trucks'] - $includedTrucks),
        ];
    }

    public function vehicleCountForTenant(Tenant $tenant): int
    {
        return Vehicle::query()
            ->withoutGlobalScopes(['tenant', 'branch'])
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();
    }
}
