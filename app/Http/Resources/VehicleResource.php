<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'registration_number' => $this->registration_number,
            'vehicle_type' => $this->vehicle_type,
            'make' => $this->make,
            'model' => $this->model,
            'manufacture_year' => $this->manufacture_year,
            'odometer_km' => $this->odometer_km,
            'primary_driver_id' => $this->when(
                $this->relationLoaded('currentPrimaryAssignment') && $this->currentPrimaryAssignment !== null,
                fn () => $this->currentPrimaryAssignment->driver_id,
            ),
            'current_primary_assignment' => $this->when(
                $this->relationLoaded('currentPrimaryAssignment') && $this->currentPrimaryAssignment !== null,
                fn (): array => [
                    'id' => $this->currentPrimaryAssignment->id,
                    'driver_id' => $this->currentPrimaryAssignment->driver_id,
                    'assigned_from' => $this->currentPrimaryAssignment->assigned_from?->toDateString(),
                    'driver' => $this->currentPrimaryAssignment->relationLoaded('driver') && $this->currentPrimaryAssignment->driver !== null
                        ? [
                            'id' => $this->currentPrimaryAssignment->driver->id,
                            'name' => $this->currentPrimaryAssignment->driver->name,
                            'phone' => $this->currentPrimaryAssignment->driver->phone,
                        ]
                        : null,
                ],
            ),
        ];
    }
}
