<?php

namespace App\Policies;

use App\Driver;
use App\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }
}
