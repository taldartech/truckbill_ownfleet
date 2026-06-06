<?php

namespace App\Policies;

use App\Emi;
use App\Support\CurrentTenant;
use App\User;

class EmiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('emis.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Emi $emi): bool
    {
        return CurrentTenant::id() === $emi->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('emis.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Emi $emi): bool
    {
        return CurrentTenant::id() === $emi->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Emi $emi): bool
    {
        return CurrentTenant::id() === $emi->tenant_id
            && $user->hasPermission('emis.delete')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
