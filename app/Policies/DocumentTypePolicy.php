<?php

namespace App\Policies;

use App\DocumentType;
use App\Support\CurrentTenant;
use App\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicle_documents.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, DocumentType $documentType): bool
    {
        return CurrentTenant::id() === $documentType->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicle_documents.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, DocumentType $documentType): bool
    {
        return CurrentTenant::id() === $documentType->tenant_id
            && $this->create($user);
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return false;
    }
}
