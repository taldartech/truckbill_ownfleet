<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\TyreLayout;
use App\User;

class TyreLayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tyre_layouts.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, TyreLayout $tyreLayout): bool
    {
        return CurrentTenant::id() === $tyreLayout->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tyre_layouts.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, TyreLayout $tyreLayout): bool
    {
        return CurrentTenant::id() === $tyreLayout->tenant_id
            && $user->hasPermission('tyre_layouts.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, TyreLayout $tyreLayout): bool
    {
        return $this->update($user, $tyreLayout);
    }
}
