<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatteryInstallationResource extends JsonResource
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
            'vehicle_registration' => $this->vehicle?->registration_number,
            'slot_name' => $this->slot_name,
            'installed_at' => $this->installed_at?->toDateString(),
            'removed_at' => $this->removed_at?->toDateString(),
            'km_at_installation' => (float) $this->km_at_installation,
            'km_at_removal' => $this->km_at_removal !== null ? (float) $this->km_at_removal : null,
            'running_km' => $this->running_km !== null ? (float) $this->running_km : null,
            'removal_reason' => $this->removal_reason,
            'notes' => $this->notes,
            'battery' => $this->whenLoaded('battery', function () {
                return [
                    'id' => $this->battery->id,
                    'serial_number' => $this->battery->serial_number,
                    'brand' => $this->battery->brand,
                    'model' => $this->battery->model,
                    'capacity_ah' => (float) $this->battery->capacity_ah,
                    'voltage' => $this->battery->voltage,
                    'status' => $this->battery->status?->value ?? (string) $this->battery->status,
                    'warranty_months' => $this->battery->warranty_months,
                    'purchase_date' => $this->battery->purchase_date?->toDateString(),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
