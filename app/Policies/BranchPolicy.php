<?php

namespace App\Policies;

use App\Branch;
use App\Support\RoleHierarchy;
use App\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->viewAny($user)
            && RoleHierarchy::actorOwnsTenant($user, (string) $branch->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.manage')
            && RoleHierarchy::actorOwnsTenant($user, (string) $branch->company_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function enter(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.enter')
            && RoleHierarchy::actorOwnsTenant($user, (string) $branch->company_id);
    }
}
