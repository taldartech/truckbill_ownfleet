<?php

namespace App\Http\Resources;

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
        $activeTenant = $effectiveTenantId !== null
            ? ($this->tenant ?? $this->ownedTenants->firstWhere('id', $effectiveTenantId))
            : null;

        if ($activeTenant === null && $effectiveTenantId !== null) {
            $activeTenant = \App\Tenant::query()->find($effectiveTenantId);
        }

        $planEndDate = $activeTenant?->plan_end_date;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'company_id' => $effectiveTenantId,
            'effective_tenant_id' => $effectiveTenantId,
            'effective_company_id' => $effectiveTenantId,
            'tenant_name' => $activeTenant?->name,
            'user_role_id' => $this->user_role_id,
            'role' => $this->userRole?->name,
            'permissions' => $this->permissionSlugs(),
            'can_access_company_panel' => $this->canAccessCompanyPanel(),
            'requires_company_selection' => $this->requiresCompanySelection(),
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
                'lr_credits' => $activeTenant?->lr_credits,
            ],
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
