<?php

namespace App\Policies;

use App\TripExpenseTypeMaster;
use App\User;

class TripExpenseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasPermission('trip_expense_types.view')
            || $user->hasPermission('trips.expenses')
            || $user->hasPermission('trips.view')
            || $user->hasPermission('trips.edit');
    }

    public function view(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        return $this->viewAny($user)
            && ($tripExpenseType->isSystem() || $tripExpenseType->super_admin_id === $user->resolvedSuperAdminId());
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasPermission('trip_expense_types.create');
    }

    public function update(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        if ($tripExpenseType->isSystem()) {
            return false;
        }

        $canEdit = $user->hasRole('super_admin')
            || $user->hasPermission('trip_expense_types.edit');

        return $canEdit && $tripExpenseType->super_admin_id === $user->resolvedSuperAdminId();
    }

    public function delete(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        if ($tripExpenseType->isSystem()) {
            return false;
        }

        $canDelete = $user->hasRole('super_admin')
            || $user->hasPermission('trip_expense_types.delete');

        return $canDelete && $tripExpenseType->super_admin_id === $user->resolvedSuperAdminId();
    }
}
