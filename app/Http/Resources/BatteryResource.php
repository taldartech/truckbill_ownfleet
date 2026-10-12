<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatteryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isInWarranty = false;
        if ($this->warranty_valid_until) {
            $isInWarranty = Carbon::parse($this->warranty_valid_until)->endOfDay()->isFuture();
        }

        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'battery_type' => $this->battery_type?->value ?? (string) $this->battery_type,
            'voltage' => $this->voltage,
            'capacity_ah' => (float) $this->capacity_ah,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => (float) $this->purchase_cost,
            'warranty_months' => (int) ($this->warranty_months ?? 0),
            'warranty_valid_until' => $this->warranty_valid_until?->toDateString(),
            'is_in_warranty' => $isInWarranty,
            'expected_life_months' => (int) ($this->expected_life_months ?? 36),
            'supplier_name' => $this->supplier_name,
            'status' => $this->status?->value ?? (string) $this->status,
            'scrapped_at' => $this->scrapped_at?->toDateString(),
            'scrap_sale_amount' => $this->scrap_sale_amount !== null ? (float) $this->scrap_sale_amount : null,
            'notes' => $this->notes,
            'active_installation' => $this->when(
                $this->relationLoaded('activeInstallation') && $this->activeInstallation !== null,
                function () {
                    $active = $this->activeInstallation;
                    $vehicleOdo = (float) ($active->vehicle?->odometer_km ?? 0);
                    $kmAtInstall = (float) ($active->km_at_installation ?? 0);
                    $stintKm = max(0, $vehicleOdo - $kmAtInstall);

                    return [
                        'id' => $active->id,
                        'vehicle_id' => $active->vehicle_id,
                        'vehicle_registration' => $active->vehicle?->registration_number,
                        'slot_name' => $active->slot_name,
                        'installed_at' => $active->installed_at?->toDateString(),
                        'km_at_installation' => $kmAtInstall,
                        'vehicle_odometer_km' => $vehicleOdo,
                        'stint_km' => $stintKm,
                    ];
                }
            ),
            'installations' => BatteryInstallationResource::collection($this->whenLoaded('installations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
