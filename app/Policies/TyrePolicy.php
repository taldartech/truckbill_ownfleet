<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\Tyre;
use App\User;

class TyrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tyres.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Tyre $tyre): bool
    {
        return CurrentTenant::id() === $tyre->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tyres.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Tyre $tyre): bool
    {
        return CurrentTenant::id() === $tyre->tenant_id
            && $user->hasPermission('tyres.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Tyre $tyre): bool
    {
        return $user->canDeleteOperationalRecords()
            && $this->update($user, $tyre);
    }
}
