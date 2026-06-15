<?php

namespace App\Policies;

use App\Attendance;
use App\Support\CurrentTenant;
use App\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendances.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return CurrentTenant::id() === $attendance->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendances.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return CurrentTenant::id() === $attendance->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->canDeleteOperationalRecords()
            && $this->update($user, $attendance);
    }

    public function bulkMark(User $user): bool
    {
        return $user->hasPermission('attendances.bulk_manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function markWagePaid(User $user, Attendance $attendance): bool
    {
        return CurrentTenant::id() === $attendance->tenant_id
            && $user->hasPermission('wages.manage')
            && $user->canAccessCompanyPanel();
    }

    public function viewSettlement(User $user): bool
    {
        return ($user->hasPermission('wages.view') || $user->hasPermission('wages.manage'))
            && $user->canAccessCompanyPanel();
    }

    public function exportSettlement(User $user): bool
    {
        return $this->viewSettlement($user);
    }

    public function closeMonth(User $user): bool
    {
        return $user->hasPermission('wages.manage')
            && $user->canAccessCompanyPanel();
    }

    public function markMonthlyWagePaid(User $user): bool
    {
        return $this->closeMonth($user);
    }

    public function unlockMonth(User $user): bool
    {
        return $user->hasRole('super_admin')
            && $user->canAccessCompanyPanel();
    }
}
