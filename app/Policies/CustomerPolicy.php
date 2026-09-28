<?php

namespace App\Policies;

use App\Customer;
use App\Support\CurrentTenant;
use App\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->hasPermission('customers.view')
            || $user->hasPermission('trips.manage')
            || $user->hasPermission('trips.create')
            || $user->hasPermission('trips.view')
            || $user->hasPermission('invoices.manage')
            || $user->hasPermission('invoices.create')
            || $user->hasPermission('invoices.view'))
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Customer $customer): bool
    {
        return CurrentTenant::id() === $customer->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('customers.create') || $user->hasPermission('trips.create'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return CurrentTenant::id() === $customer->tenant_id
            && $user->hasPermission('customers.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
