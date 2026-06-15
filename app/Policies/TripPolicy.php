<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\Trip;
use App\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('trips.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Trip $trip): bool
    {
        return CurrentTenant::id() === $trip->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('trips.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Trip $trip): bool
    {
        return CurrentTenant::id() === $trip->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $user->canDeleteOperationalRecords()
            && $this->update($user, $trip);
    }
}
