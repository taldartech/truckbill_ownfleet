<?php

namespace App\Policies;

use App\Support\RoleHierarchy;
use App\User;

class ManagedUserPolicy
{
    public function viewAny(User $user): bool
    {
        if (RoleHierarchy::managesSuperAdmins($user)) {
            return $user->hasPermission('super_admins.view');
        }

        if (RoleHierarchy::managesTenantUsers($user)) {
            return $user->hasPermission('users.view');
        }

        return false;
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user) && RoleHierarchy::canManageUser($user, $model);
    }

    public function create(User $user): bool
    {
        if (RoleHierarchy::managesSuperAdmins($user)) {
            return $user->hasPermission('super_admins.manage');
        }

        if (RoleHierarchy::managesTenantUsers($user)) {
            return $user->hasPermission('users.manage');
        }

        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $this->create($user) && RoleHierarchy::canManageUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->create($user) && RoleHierarchy::canDeleteUser($user, $model);
    }
}
