<?php

namespace App\Policies;

use App\GoodsType;
use App\Support\CurrentTenant;
use App\User;

class GoodsTypePolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('goods_types.view')
            || $user->hasPermission('trips.view')
            || $user->hasPermission('trips.create')
            || $user->hasPermission('trips.manage'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, GoodsType $goodsType): bool
    {
        return CurrentTenant::id() === $goodsType->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('goods_types.manage')
            || $user->hasPermission('goods_types.create')
            || $user->hasPermission('trips.create'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, GoodsType $goodsType): bool
    {
        return CurrentTenant::id() === $goodsType->tenant_id
            && ($user->hasPermission('goods_types.manage') || $user->hasPermission('goods_types.edit'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, GoodsType $goodsType): bool
    {
        return CurrentTenant::id() === $goodsType->tenant_id
            && ($user->hasPermission('goods_types.manage') || $user->hasPermission('goods_types.edit'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
