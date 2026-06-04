<?php

namespace App\Policies;

use App\State;
use App\Support\IndiaCountry;
use App\User;

class StatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, State $state): bool
    {
        return $state->country_id === IndiaCountry::id()
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('locations.manage')
            && $user->canAccessCompanyPanel();
    }

    public function update(User $user, State $state): bool
    {
        return $state->country_id === IndiaCountry::id()
            && $user->hasPermission('locations.manage')
            && $user->canAccessCompanyPanel();
    }

    public function delete(User $user, State $state): bool
    {
        return false;
    }
}
