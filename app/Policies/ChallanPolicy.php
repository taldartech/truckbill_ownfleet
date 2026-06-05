<?php

namespace App\Policies;

use App\Challan;
use App\Support\CurrentTenant;
use App\User;

class ChallanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('challans.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Challan $challan): bool
    {
        return CurrentTenant::id() === $challan->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('challans.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Challan $challan): bool
    {
        return CurrentTenant::id() === $challan->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Challan $challan): bool
    {
        return CurrentTenant::id() === $challan->tenant_id
            && $user->hasPermission('challans.delete')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
