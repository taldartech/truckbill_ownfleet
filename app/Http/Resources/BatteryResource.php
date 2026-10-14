<?php

namespace App\Http\Resources;

use App\Battery;
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
        $warrantyStatus = 'none';
        if ($this->warranty_valid_until) {
            $warrantyEnd = Carbon::parse($this->warranty_valid_until)->endOfDay();
            $isInWarranty = $warrantyEnd->isFuture();
            $warrantyStatus = match (true) {
                ! $isInWarranty => 'expired',
                $warrantyEnd->lte(now()->addDays(Battery::WARRANTY_EXPIRING_DAYS)->endOfDay()) => 'expiring',
                default => 'active',
            };
        }

        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'battery_type' => $this->battery_type?->value ?? (string) $this->battery_type,
            'battery_type_label' => $this->battery_type?->label(),
            'voltage' => $this->voltage,
            'capacity_ah' => (float) $this->capacity_ah,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => (float) $this->purchase_cost,
            'warranty_months' => (int) ($this->warranty_months ?? 0),
            'warranty_valid_until' => $this->warranty_valid_until?->toDateString(),
            'is_in_warranty' => $isInWarranty,
            'warranty_status' => $warrantyStatus,
            'expected_life_months' => (int) ($this->expected_life_months ?? 36),
            'supplier_name' => $this->supplier_name,
            'status' => $this->status?->value ?? (string) $this->status,
            'status_label' => $this->status?->label(),
            'scrapped_at' => $this->scrapped_at?->toDateString(),
            'scrap_sale_amount' => $this->scrap_sale_amount !== null ? (float) $this->scrap_sale_amount : null,
            'scrap_notes' => $this->scrap_notes,
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
                        'battery_id' => $active->battery_id,
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
            'inspections' => BatteryInspectionResource::collection($this->whenLoaded('inspections')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
