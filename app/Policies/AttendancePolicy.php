<?php

namespace App\Policies;

use App\Attendance;
use App\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->tenant_id === $attendance->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER', 'ACCOUNTANT']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->tenant_id === $attendance->tenant_id
            && $user->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->update($user, $attendance);
    }
}
