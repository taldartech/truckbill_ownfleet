<?php

namespace App\Http\Resources;

use App\Enums\BatteryCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatteryInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'battery_id' => $this->battery_id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle_registration' => $this->whenLoaded('vehicle', fn () => $this->vehicle?->registration_number),
            'inspected_at' => $this->inspected_at?->toDateString(),
            'voltage_reading' => $this->voltage_reading !== null ? (float) $this->voltage_reading : null,
            'specific_gravity' => $this->specific_gravity !== null ? (float) $this->specific_gravity : null,
            'condition' => $this->condition,
            'condition_label' => BatteryCondition::tryFrom((string) $this->condition)?->label() ?? $this->condition,
            'action_taken' => $this->action_taken,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
