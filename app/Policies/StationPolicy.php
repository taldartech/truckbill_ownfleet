<?php

namespace App\Policies;

use App\Station;
use App\Support\CurrentTenant;
use App\User;

class StationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stations.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Station $station): bool
    {
        return CurrentTenant::id() === $station->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('stations.manage') || $user->hasPermission('trips.manage'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Station $station): bool
    {
        return CurrentTenant::id() === $station->tenant_id
            && $user->hasPermission('stations.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Station $station): bool
    {
        return $this->update($user, $station);
    }
}
