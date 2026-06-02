<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\User;
use App\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicles.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicles.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return CurrentTenant::id() === $vehicle->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }
}
