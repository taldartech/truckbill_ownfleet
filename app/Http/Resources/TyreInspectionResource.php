<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tyre_id' => $this->tyre_id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle_registration' => $this->whenLoaded('vehicle', fn () => $this->vehicle?->registration_number),
            'inspected_at' => $this->inspected_at?->toDateString(),
            'tread_depth_mm' => $this->tread_depth_mm !== null ? (float) $this->tread_depth_mm : null,
            'pressure_psi' => $this->pressure_psi !== null ? (float) $this->pressure_psi : null,
            'result' => $this->result,
            'next_due_at' => $this->next_due_at?->toDateString(),
            'notes' => $this->notes,
            'inspector_id' => $this->inspector_id,
            'inspector_name' => $this->whenLoaded('inspector', fn () => $this->inspector?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
