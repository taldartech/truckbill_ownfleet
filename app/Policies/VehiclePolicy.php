<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\User;
use App\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('vehicles.view')
            || $user->hasPermission('trips.manage')
            || $user->hasPermission('trips.create')
            || $user->hasPermission('attendances.manage')
            || $user->hasPermission('attendances.create')
            || $user->hasPermission('attendance.bulk_mark'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicles.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $user->hasPermission('vehicles.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $user->hasPermission('vehicles.delete')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function toggleStatus(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $user->hasPermission('vehicles.toggle_status')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
