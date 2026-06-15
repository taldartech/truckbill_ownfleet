<?php

namespace App\Policies;

use App\Driver;
use App\Support\CurrentTenant;
use App\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('drivers.view')
            || $user->hasPermission('trips.manage')
            || $user->hasPermission('attendances.manage'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('drivers.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }
}
