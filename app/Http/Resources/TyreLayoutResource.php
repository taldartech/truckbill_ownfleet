<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreLayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'layout_type' => $this->layout_type,
            'steer_axles' => (int) $this->steer_axles,
            'dual_axles' => $this->dual_axles ?? [],
            'single_axles' => $this->single_axles ?? [],
            'spare_count' => (int) $this->spare_count,
            'position_count' => $this->positionSlotCount(),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'is_in_use' => Vehicle::query()
                ->where('tenant_id', $this->tenant_id)
                ->where('tyre_layout', $this->code)
                ->exists(),
            'created_at' => $this->created_at ? ApiDateTime::toIso($this->created_at) : null,
            'updated_at' => $this->updated_at ? ApiDateTime::toIso($this->updated_at) : null,
            'created_by_name' => null,
            'updated_by_name' => null,
        ];
    }
}
