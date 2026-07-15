<?php

namespace App\Policies;

use App\Enums\TripStatus;
use App\Support\CurrentTenant;
use App\Support\TripArchiveImpact;
use App\Trip;
use App\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('trips.view')
            && $user->canAccessCompanyPanel();
    }

    public function viewArchived(User $user): bool
    {
        return $user->hasPermission('trips.view_archived')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Trip $trip): bool
    {
        return CurrentTenant::id() === $trip->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('trips.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Trip $trip): bool
    {
        return ! $trip->trashed()
            && CurrentTenant::id() === $trip->tenant_id
            && $user->hasPermission('trips.edit')
            && $user->canAccessCompanyPanel();
    }

    public function archive(User $user, Trip $trip): bool
    {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        return ! $trip->trashed()
            && CurrentTenant::id() === $trip->tenant_id
            && ($user->hasPermission('trips.archive') || $user->hasPermission('trips.delete'))
            && TripArchiveImpact::isArchivable($status, $user)
            && $this->viewAny($user);
    }

    public function restore(User $user, Trip $trip): bool
    {
        return $trip->trashed()
            && CurrentTenant::id() === $trip->tenant_id
            && $user->hasPermission('trips.restore')
            && $this->viewAny($user);
    }

    /**
     * @deprecated Use archive()
     */
    public function delete(User $user, Trip $trip): bool
    {
        return $this->archive($user, $trip);
    }
}
