<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'country_name' => $this->country?->name,
            'name' => $this->name,
            'code' => $this->code,
            'cities_count' => $this->whenCounted('cities'),
            'label' => $this->name,
            'created_at' => $this->created_at ? ApiDateTime::toIso($this->created_at) : null,
            'updated_at' => $this->updated_at ? ApiDateTime::toIso($this->updated_at) : null,
            'created_by_name' => null,
            'updated_by_name' => null,
        ];
    }
}
