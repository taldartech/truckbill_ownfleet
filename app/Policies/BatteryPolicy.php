<?php

namespace App\Policies;

use App\Battery;
use App\Support\CurrentTenant;
use App\User;

class BatteryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('batteries.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Battery $battery): bool
    {
        return CurrentTenant::id() === $battery->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('batteries.manage') || $user->hasPermission('batteries.create'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Battery $battery): bool
    {
        return CurrentTenant::id() === $battery->tenant_id
            && ($user->hasPermission('batteries.manage') || $user->hasPermission('batteries.edit'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Battery $battery): bool
    {
        return $user->canDeleteOperationalRecords()
            && ($user->hasPermission('batteries.manage') || $user->hasPermission('batteries.delete'))
            && CurrentTenant::id() === $battery->tenant_id
            && $user->canAccessCompanyPanel();
    }
}
