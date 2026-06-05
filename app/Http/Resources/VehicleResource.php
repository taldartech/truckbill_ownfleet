<?php

namespace App\Http\Resources;

use App\Support\TyreLayoutDefaults;
use App\TyreLayout;
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
            'tyre_layout' => $this->tyre_layout ?? TyreLayoutDefaults::defaultCode(),
            'tyre_layout_label' => $this->resolveTyreLayoutLabel(),
            'make' => $this->make,
            'model' => $this->model,
            'manufacture_year' => $this->manufacture_year,
            'odometer_km' => $this->odometer_km,
            'is_active' => (bool) $this->is_active,
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

    private function resolveTyreLayoutLabel(): string
    {
        $layout = TyreLayout::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('code', $this->tyre_layout)
            ->first();

        return $layout?->name ?? (string) ($this->tyre_layout ?? TyreLayoutDefaults::defaultCode());
    }
}
