<?php

namespace App\Http\Resources;

use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant?->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->branch_name,
            'user_role_id' => $this->user_role_id,
            'role' => $this->userRole?->name,
            'role_label' => $this->user_role_id
                ? RoleHierarchy::roleLabel((int) $this->user_role_id)
                : null,
            'is_protected' => RoleHierarchy::isProtectedRole((int) $this->user_role_id),
            'document_alerts_live' => (bool) $this->document_alerts_live,
            'document_alerts_email' => (bool) $this->document_alerts_email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
