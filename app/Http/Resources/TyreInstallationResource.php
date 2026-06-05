<?php

namespace App\Http\Resources;

use App\Support\TyrePositionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreInstallationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tyre_id' => $this->tyre_id,
            'vehicle_id' => $this->vehicle_id,
            'vehicle_registration' => $this->vehicle?->registration_number,
            'position' => $this->position_code,
            'position_label' => (new TyrePositionCatalog)->labelFor($this->position_code),
            'installed_at' => $this->installed_at?->toDateString(),
            'removed_at' => $this->removed_at?->toDateString(),
            'km_at_installation' => (float) $this->km_at_installation,
            'km_at_removal' => $this->km_at_removal !== null ? (float) $this->km_at_removal : null,
            'running_km' => $this->running_km !== null ? (float) $this->running_km : null,
            'removal_recommendation' => $this->removal_recommendation,
            'removal_advice' => $this->when(isset($this->removal_advice), $this->removal_advice),
        ];
    }
}
