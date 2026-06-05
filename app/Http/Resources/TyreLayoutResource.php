<?php

namespace App\Http\Resources;

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
        ];
    }
}
