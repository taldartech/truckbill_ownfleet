<?php

namespace App\Policies;

use App\Tenant;
use App\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->ownsOrMaster($user, $tenant);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('companies.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('companies.update')
            && $this->ownsOrMaster($user, $tenant);
    }

    public function enter(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('companies.enter')
            && $tenant->owner_user_id === $user->id
            && ! $tenant->is_blocked
            && $tenant->is_active;
    }

    public function block(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('companies.block');
    }

    private function ownsOrMaster(User $user, Tenant $tenant): bool
    {
        if ($user->hasPermission('companies.block')) {
            return true;
        }

        return $user->hasPermission('companies.update')
            && $tenant->owner_user_id === $user->id;
    }
}
