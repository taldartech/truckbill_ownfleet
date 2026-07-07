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
            || $user->hasPermission('trips.create')
            || $user->hasPermission('trips.manage')
            || $user->hasPermission('attendances.create')
            || $user->hasPermission('attendances.manage')
            || $user->hasPermission('attendance.bulk_mark'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('drivers.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $user->hasPermission('drivers.edit')
            && $user->canAccessCompanyPanel();
    }

    public function delete(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $user->hasPermission('drivers.archive')
            && $user->canAccessCompanyPanel();
    }

    public function viewAdvances(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && ($user->hasPermission('driver_advances.view') || $user->hasPermission('drivers.view'))
            && $user->canAccessCompanyPanel();
    }

    public function manageAdvances(User $user, Driver $driver): bool
    {
        return CurrentTenant::id() === $driver->tenant_id
            && $user->hasPermission('driver_advances.manage')
            && $user->canAccessCompanyPanel();
    }
}
