<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreDamageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'damage_type' => $this->damage_type?->value,
            'position' => $this->position_code?->value ?? $this->position_code,
            'position_label' => $this->position_code instanceof \App\Enums\TyrePosition
                ? $this->position_code->label()
                : $this->position_code,
            'km_at_occurrence' => $this->km_at_occurrence !== null ? (float) $this->km_at_occurrence : null,
            'occurred_at' => $this->occurred_at?->toDateString(),
            'description' => $this->description,
            'repair_cost' => $this->repair_cost !== null ? (float) $this->repair_cost : null,
            'is_repaired' => (bool) $this->is_repaired,
            'vehicle_registration' => $this->vehicle?->registration_number,
        ];
    }
}
