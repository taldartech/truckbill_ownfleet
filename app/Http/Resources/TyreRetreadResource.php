<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreRetreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor' => $this->vendor,
            'cost' => (float) $this->cost,
            'retread_date' => $this->retread_date?->toDateString(),
            'condition_after' => $this->condition_after,
            'notes' => $this->notes,
        ];
    }
}
