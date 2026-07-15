<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrokerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cityName = $this->city?->name;
        $stateName = $this->state?->name;
        $countryName = $this->country?->name;

        $locationParts = array_filter([$cityName, $stateName]);
        $locationLabel = $locationParts !== [] ? implode(', ', $locationParts) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $locationLabel ? "{$this->name} — {$locationLabel}" : $this->name,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'agency_id' => $this->agency_id,
            'gst_number' => $this->gst_number,
            'pan_number' => $this->pan_number,
            'address' => $this->address,
            'contact_number' => $this->contact_number,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country?->id,
                'name' => $this->country?->name,
            ]),
            'state' => $this->whenLoaded('state', fn () => [
                'id' => $this->state?->id,
                'name' => $this->state?->name,
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name' => $this->city?->name,
            ]),
            'country_name' => $countryName,
            'state_name' => $stateName,
            'city_name' => $cityName,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'updated_by_name' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
