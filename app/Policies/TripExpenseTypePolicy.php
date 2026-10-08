<?php

namespace App\Policies;

use App\TripExpenseTypeMaster;
use App\User;

class TripExpenseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'master_admin', 'admin'])
            || $user->hasPermission('trip_expense_types.view')
            || $user->hasPermission('trip_expense_types.manage')
            || $user->hasPermission('trips.expenses')
            || $user->hasPermission('trips.view')
            || $user->hasPermission('trips.edit');
    }

    public function view(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'master_admin', 'admin'])
            || $user->hasPermission('trip_expense_types.manage')
            || $user->hasPermission('trip_expense_types.create');
    }

    public function update(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        return $user->hasAnyRole(['super_admin', 'master_admin', 'admin'])
            || $user->hasPermission('trip_expense_types.manage')
            || $user->hasPermission('trip_expense_types.edit');
    }

    public function delete(User $user, TripExpenseTypeMaster $tripExpenseType): bool
    {
        return $user->hasAnyRole(['super_admin', 'master_admin', 'admin'])
            || $user->hasPermission('trip_expense_types.manage')
            || $user->hasPermission('trip_expense_types.edit');
    }
}
