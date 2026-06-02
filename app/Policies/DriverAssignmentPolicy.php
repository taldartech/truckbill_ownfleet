<?php

namespace App\Policies;

use App\DriverAssignment;
use App\Support\CurrentTenant;
use App\User;

class DriverAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('driver_assignments.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, DriverAssignment $driverAssignment): bool
    {
        return CurrentTenant::id() === $driverAssignment->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('driver_assignments.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, DriverAssignment $driverAssignment): bool
    {
        return CurrentTenant::id() === $driverAssignment->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, DriverAssignment $driverAssignment): bool
    {
        return $this->update($user, $driverAssignment);
    }
}
