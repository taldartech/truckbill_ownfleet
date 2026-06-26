<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleMaintenanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'service_date' => $this->service_date?->toDateString(),
            'odometer_km' => $this->odometer_km,
            'maintenance_service_type_id' => $this->maintenance_service_type_id,
            'service_type' => $this->whenLoaded(
                'maintenanceServiceType',
                fn () => $this->maintenanceServiceType?->code,
            ),
            'service_type_label' => $this->whenLoaded(
                'maintenanceServiceType',
                fn () => $this->maintenanceServiceType?->name,
            ),
            'service_provider' => $this->service_provider,
            'cost_amount' => $this->cost_amount,
            'next_service_due_km' => $this->next_service_due_km,
            'next_service_due_date' => $this->next_service_due_date?->toDateString(),
            'remarks' => $this->remarks,
            'vehicle' => $this->whenLoaded('vehicle', fn (): array => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'is_active' => (bool) $this->vehicle->is_active,
            ]),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
