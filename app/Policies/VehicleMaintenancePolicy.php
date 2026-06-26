<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\User;
use App\VehicleMaintenance;

class VehicleMaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, VehicleMaintenance $vehicleMaintenance): bool
    {
        return CurrentTenant::id() === $vehicleMaintenance->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, VehicleMaintenance $vehicleMaintenance): bool
    {
        return CurrentTenant::id() === $vehicleMaintenance->tenant_id
            && $user->hasPermission('maintenance.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, VehicleMaintenance $vehicleMaintenance): bool
    {
        return CurrentTenant::id() === $vehicleMaintenance->tenant_id
            && $user->hasPermission('maintenance.delete')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
