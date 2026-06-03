<?php

namespace App\Policies;

use App\Support\RoleHierarchy;
use App\User;
use App\UserRole;

class UserRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, UserRole $userRole): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, UserRole $userRole): bool
    {
        return $user->hasPermission('roles.manage')
            && RoleHierarchy::canModifyRolePermissions($user, $userRole);
    }
}
