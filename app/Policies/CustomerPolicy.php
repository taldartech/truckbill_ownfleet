<?php

namespace App\Policies;

use App\Customer;
use App\Support\CurrentTenant;
use App\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Customer $customer): bool
    {
        return CurrentTenant::id() === $customer->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ($user->hasPermission('customers.manage') || $user->hasPermission('trips.manage'))
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return CurrentTenant::id() === $customer->tenant_id
            && $user->hasPermission('customers.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
