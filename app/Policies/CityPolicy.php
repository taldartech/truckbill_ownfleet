<?php

namespace App\Policies;

use App\City;
use App\Support\IndiaCountry;
use App\User;

class CityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, City $city): bool
    {
        return $city->state()->where('country_id', IndiaCountry::id())->exists()
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('locations.manage')
            && $user->canAccessCompanyPanel();
    }

    public function update(User $user, City $city): bool
    {
        return $city->state()->where('country_id', IndiaCountry::id())->exists()
            && $user->hasPermission('locations.manage')
            && $user->canAccessCompanyPanel();
    }

    public function delete(User $user, City $city): bool
    {
        return false;
    }
}
