<?php

namespace App\Policies;

use App\Broker;
use App\Support\CurrentTenant;
use App\User;

class BrokerPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('brokers.view')
            || $user->hasPermission('trips.manage'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Broker $broker): bool
    {
        return CurrentTenant::id() === $broker->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('brokers.create') || $user->hasPermission('trips.create'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Broker $broker): bool
    {
        return CurrentTenant::id() === $broker->tenant_id
            && $user->hasPermission('brokers.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Broker $broker): bool
    {
        return $this->update($user, $broker);
    }
}
