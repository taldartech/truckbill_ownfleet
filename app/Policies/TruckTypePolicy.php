<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\TruckType;
use App\User;

class TruckTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicles.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, TruckType $truckType): bool
    {
        return CurrentTenant::id() === $truckType->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicles.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, TruckType $truckType): bool
    {
        return CurrentTenant::id() === $truckType->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, TruckType $truckType): bool
    {
        return false;
    }
}
