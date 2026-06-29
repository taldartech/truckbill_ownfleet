<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Support\RoleHierarchy;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $effectiveTenantId = $this->effectiveTenantId();
        $effectiveBranchId = $this->effectiveBranchId();
        $activeTenant = $effectiveTenantId !== null
            ? ($this->tenant ?? $this->ownedTenants->firstWhere('id', $effectiveTenantId))
            : null;

        if ($activeTenant === null && $effectiveTenantId !== null) {
            $activeTenant = \App\Tenant::query()->find($effectiveTenantId);
        }

        $activeBranch = $effectiveBranchId !== null
            ? \App\Branch::query()->find($effectiveBranchId)
            : $this->branch;

        if ($activeTenant !== null) {
            $activeTenant->loadMissing('subscription');
        }

        $planEndDate = $activeTenant?->subscription?->ends_on;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'company_id' => $effectiveTenantId,
            'effective_tenant_id' => $effectiveTenantId,
            'effective_company_id' => $effectiveTenantId,
            'tenant_name' => $activeTenant?->name,
            'branch_id' => $effectiveBranchId ?? $this->branch_id,
            'effective_branch_id' => $effectiveBranchId,
            'branch_name' => $activeBranch?->branch_name,
            'user_role_id' => $this->user_role_id,
            'role' => $this->userRole?->name,
            'role_label' => $this->user_role_id
                ? RoleHierarchy::roleLabel((int) $this->user_role_id)
                : null,
            'permissions' => $this->permissionSlugs(),
            'can_delete_records' => $this->canDeleteOperationalRecords(),
            'can_access_company_panel' => $this->canAccessCompanyPanel(),
            'requires_company_selection' => $this->requiresCompanySelection(),
            'requires_branch_selection' => $this->requiresBranchSelection(),
            'show_plan_badges' => in_array($this->user_role_id, [
                UserRole::ADMIN,
                UserRole::MODERATOR,
                UserRole::ACCOUNTANT,
            ], true) || ($this->userRole?->name === 'super_admin' && $this->canAccessCompanyPanel()),
            'company' => [
                'id' => $effectiveTenantId,
                'tenant_id' => $effectiveTenantId,
                'company_id' => $effectiveTenantId,
                'name' => $activeTenant?->name,
                'plan_end_date_formatted' => $planEndDate
                    ? Carbon::parse($planEndDate)->format('d M, Y')
                    : null,
                'lr_credits' => $activeTenant?->subscription?->lr_credits_remaining ?? 0,
            ],
            'subscription' => $activeTenant?->subscriptionAccessPayload(),
            'email_verified_at' => ApiDateTime::toIso($this->email_verified_at),
            'created_at' => ApiDateTime::toIso($this->created_at),
        ];
    }
}
