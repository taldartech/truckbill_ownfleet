<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallanResource extends JsonResource
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
            'driver_id' => $this->driver_id,
            'challan_number' => $this->challan_number,
            'challan_date' => $this->challan_date?->toDateString(),
            'violation_type' => $this->violation_type,
            'amount' => $this->amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'vehicle' => $this->whenLoaded('vehicle', fn (): array => [
                'id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
            ]),
            'driver' => $this->whenLoaded('driver', fn (): array => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
