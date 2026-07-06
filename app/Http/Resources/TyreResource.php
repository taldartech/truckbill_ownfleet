<?php

namespace App\Http\Resources;

use App\Support\TyrePositionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'brand' => $this->brand,
            'size' => $this->size,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => (float) $this->purchase_cost,
            'warranty_valid_until' => $this->warranty_valid_until?->toDateString(),
            'warranty_km' => $this->warranty_km !== null ? (float) $this->warranty_km : null,
            'tyre_type' => $this->tyre_type?->value,
            'current_km' => (float) $this->current_km,
            'expected_life_km' => (float) ($this->expected_life_km ?? 40000),
            'status' => $this->status?->value,
            'scrapped_at' => $this->scrapped_at?->toDateString(),
            'scrap_notes' => $this->scrap_notes,
            'scrap_sale_amount' => $this->scrap_sale_amount !== null ? (float) $this->scrap_sale_amount : null,
            'notes' => $this->notes,
            'last_inspection_at' => $this->last_inspection_at?->toDateString(),
            'last_tread_depth_mm' => $this->last_tread_depth_mm !== null ? (float) $this->last_tread_depth_mm : null,
            'active_installation' => $this->when(
                $this->relationLoaded('activeInstallation') && $this->activeInstallation !== null,
                function () {
                    $active = $this->activeInstallation;

                    return [
                        'id' => $active->id,
                        'installation_id' => $active->id,
                        'vehicle_id' => $active->vehicle_id,
                        'vehicle_registration' => $active->vehicle?->registration_number,
                        'position' => $active->position_code,
                        'position_label' => (new TyrePositionCatalog)->labelFor($active->position_code),
                        'installed_at' => $active->installed_at?->toDateString(),
                        'km_at_installation' => (float) $active->km_at_installation,
                        'vehicle_odometer_km' => (float) ($active->vehicle?->odometer_km ?? 0),
                        'stint_km' => (float) ($active->stint_km ?? 0),
                    ];
                }
            ),
            'installations' => TyreInstallationResource::collection($this->whenLoaded('installations')),
            'rotations' => TyreRotationResource::collection($this->whenLoaded('rotations')),
            'damages' => TyreDamageResource::collection($this->whenLoaded('damages')),
            'expenses' => TyreExpenseResource::collection($this->whenLoaded('expenses')),
            'inspections' => TyreInspectionResource::collection($this->whenLoaded('inspections')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
