<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatteryReplacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle_registration' => $this->vehicle?->registration_number,
            'slot_name' => $this->slot_name,
            'old_battery' => $this->oldBattery ? [
                'id' => $this->oldBattery->id,
                'serial_number' => $this->oldBattery->serial_number,
                'brand' => $this->oldBattery->brand,
                'model' => $this->oldBattery->model,
                'status' => $this->oldBattery->status?->value ?? (string) $this->oldBattery->status,
            ] : null,
            'new_battery' => $this->newBattery ? [
                'id' => $this->newBattery->id,
                'serial_number' => $this->newBattery->serial_number,
                'brand' => $this->newBattery->brand,
                'model' => $this->newBattery->model,
                'status' => $this->newBattery->status?->value ?? (string) $this->newBattery->status,
            ] : null,
            'replaced_at' => $this->replaced_at?->toDateString(),
            'odometer_km' => (float) $this->odometer_km,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
