<?php

namespace App\Policies;

use App\Station;
use App\Support\CurrentTenant;
use App\User;

class StationPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('stations.view')
            || $user->hasPermission('trips.manage')
            || $user->hasPermission('trips.create')
            || $user->hasPermission('trips.view'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Station $station): bool
    {
        return CurrentTenant::id() === $station->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('stations.create') || $user->hasPermission('trips.create'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Station $station): bool
    {
        return CurrentTenant::id() === $station->tenant_id
            && $user->hasPermission('stations.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Station $station): bool
    {
        return $user->canDeleteOperationalRecords()
            && $this->update($user, $station);
    }
}
