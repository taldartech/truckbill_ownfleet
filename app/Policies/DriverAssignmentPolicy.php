<?php

namespace App\Policies;

use App\DriverAssignment;
use App\User;

class DriverAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function view(User $user, DriverAssignment $driverAssignment): bool
    {
        return $user->tenant_id === $driverAssignment->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function update(User $user, DriverAssignment $driverAssignment): bool
    {
        return $user->tenant_id === $driverAssignment->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function delete(User $user, DriverAssignment $driverAssignment): bool
    {
        return $this->update($user, $driverAssignment);
    }
}
