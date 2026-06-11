<?php

namespace App\Http\Resources;

use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\TruckType;
use App\TyreLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * @var array<string, string>
     */
    private static array $tyreLayoutLabelCache = [];

    /**
     * @var array<string, string>
     */
    private static array $truckTypeLabelCache = [];

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
            'vehicle_type_label' => $this->resolveTruckTypeLabel(),
            'tyre_layout' => $this->tyre_layout ?? TyreLayoutDefaults::defaultCode(),
            'tyre_layout_label' => $this->resolveTyreLayoutLabel(),
            'make' => $this->make,
            'model' => $this->model,
            'manufacture_year' => $this->manufacture_year,
            'odometer_km' => $this->odometer_km,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
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

    private function resolveTruckTypeLabel(): string
    {
        $code = (string) ($this->vehicle_type ?? TruckTypeDefaults::defaultCode());
        $cacheKey = $this->tenant_id.'|'.$code;

        if (isset(self::$truckTypeLabelCache[$cacheKey])) {
            return self::$truckTypeLabelCache[$cacheKey];
        }

        $type = TruckType::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('code', $code)
            ->first();

        self::$truckTypeLabelCache[$cacheKey] = $type?->name ?? $code;

        return self::$truckTypeLabelCache[$cacheKey];
    }

    private function resolveTyreLayoutLabel(): string
    {
        $cacheKey = $this->tenant_id.'|'.($this->tyre_layout ?? TyreLayoutDefaults::defaultCode());

        if (isset(self::$tyreLayoutLabelCache[$cacheKey])) {
            return self::$tyreLayoutLabelCache[$cacheKey];
        }

        $layout = TyreLayout::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('code', $this->tyre_layout)
            ->first();

        self::$tyreLayoutLabelCache[$cacheKey] = $layout?->name ?? (string) ($this->tyre_layout ?? TyreLayoutDefaults::defaultCode());

        return self::$tyreLayoutLabelCache[$cacheKey];
    }
}
