<?php

namespace App\Policies;

use App\MaintenanceServiceType;
use App\Support\CurrentTenant;
use App\User;

class MaintenanceServiceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, MaintenanceServiceType $maintenanceServiceType): bool
    {
        return CurrentTenant::id() === $maintenanceServiceType->tenant_id
            && $this->viewAny($user);
    }
}
