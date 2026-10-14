<?php

namespace App\Policies;

use App\Invoice;
use App\Support\CurrentTenant;
use App\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view')
            && $user->canAccessCompanyPanel();
    }

    public function viewArchived(User $user): bool
    {
        return $user->hasPermission('invoices.view_archived')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (CurrentTenant::id() !== $invoice->tenant_id || ! $user->canAccessCompanyPanel()) {
            return false;
        }

        if ($invoice->trashed()) {
            return $user->hasPermission('invoices.view_archived');
        }

        return $user->hasPermission('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return ! $invoice->trashed()
            && ! $invoice->isCancelled()
            && CurrentTenant::id() === $invoice->tenant_id
            && $user->hasPermission('invoices.edit')
            && $user->canAccessCompanyPanel();
    }

    public function updateCharges(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice)
            && $invoice->canEditCharges();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return ! $invoice->trashed()
            && ! $invoice->isCancelled()
            && CurrentTenant::id() === $invoice->tenant_id
            && $user->hasPermission('invoices.delete')
            && $user->canAccessCompanyPanel();
    }

    public function softDelete(User $user, Invoice $invoice): bool
    {
        return ! $invoice->trashed()
            && CurrentTenant::id() === $invoice->tenant_id
            && $user->hasPermission('invoices.delete')
            && $user->canAccessCompanyPanel();
    }

    public function export(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice)
            && ($user->hasPermission('invoices.export') || $user->hasPermission('invoices.view'));
    }
}
