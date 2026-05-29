<?php

namespace App\Policies;

use App\Driver;
use App\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'super_admin',
            'master_admin',
            'fleet_manager',
            'accountant',
        ]);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'super_admin',
            'master_admin',
            'fleet_manager',
        ]);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }
}
