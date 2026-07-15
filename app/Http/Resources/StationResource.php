<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
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
            // Stations do not maintain creator/updater user references. Therefore, these fields are intentionally returned as null to keep the API response consistent with other master modules.
            'created_by_name' => null,
            'updated_by_name' => null,
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
