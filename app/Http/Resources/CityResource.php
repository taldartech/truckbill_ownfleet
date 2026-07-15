<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
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
            'created_at' => $this->created_at ? ApiDateTime::toIso($this->created_at) : null,
            'updated_at' => $this->updated_at ? ApiDateTime::toIso($this->updated_at) : null,
            'created_by_name' => null,
            'updated_by_name' => null,
        ];
    }
}
