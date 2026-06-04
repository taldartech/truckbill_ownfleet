<?php

namespace App\Http\Resources;

use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => RoleHierarchy::roleLabel((int) $this->id),
            'is_protected' => RoleHierarchy::isProtectedRole((int) $this->id),
            'is_super_admin' => RoleHierarchy::isSuperAdminRole((int) $this->id),
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
