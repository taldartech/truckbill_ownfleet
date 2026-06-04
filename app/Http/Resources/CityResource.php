<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state_id' => $this->state_id,
            'state_name' => $this->whenLoaded('state', fn () => $this->state->name),
            'name' => $this->name,
            'label' => $this->name,
        ];
    }
}
