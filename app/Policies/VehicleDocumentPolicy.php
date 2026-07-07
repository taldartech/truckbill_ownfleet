<?php

namespace App\Policies;

use App\Support\CurrentTenant;
use App\User;
use App\VehicleDocument;

class VehicleDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicle_documents.view')
            && $user->canAccessCompanyPanel();
    }

    public function view(User $user, VehicleDocument $vehicleDocument): bool
    {
        return CurrentTenant::id() === $vehicleDocument->tenant_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicle_documents.create')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function update(User $user, VehicleDocument $vehicleDocument): bool
    {
        return CurrentTenant::id() === $vehicleDocument->tenant_id
            && $user->hasPermission('vehicle_documents.edit')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }

    public function delete(User $user, VehicleDocument $vehicleDocument): bool
    {
        return $user->canDeleteOperationalRecords()
            && CurrentTenant::id() === $vehicleDocument->tenant_id
            && $user->hasPermission('vehicle_documents.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null;
    }
}
