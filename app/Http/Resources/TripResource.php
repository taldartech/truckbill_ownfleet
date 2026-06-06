<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'trip_number' => $this->trip_number,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'customer_name' => $this->customer_name,
            'broker_name' => $this->broker_name,
            'start_station_id' => $this->start_station_id,
            'status' => $this->status?->value ?? $this->status,
            'start_at' => $this->start_at?->toISOString(),
            'end_at' => $this->end_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'actual_arrival_at' => $this->actual_arrival_at?->toISOString(),
            'total_distance_km' => $this->total_distance_km,
            'loaded_distance_km' => $this->loaded_distance_km,
            'half_loaded_distance_km' => $this->half_loaded_distance_km,
            'empty_distance_km' => $this->empty_distance_km,
            'total_expense_amount' => $this->total_expense_amount,
            'total_freight_amount' => $this->total_freight_amount,
            'profit_amount' => $this->profitAmount(),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'driver' => new DriverResource($this->whenLoaded('driver')),
            'route_legs' => TripRouteLegResource::collection($this->whenLoaded('routeLegs')),
            'expenses' => TripExpenseResource::collection($this->whenLoaded('expenses')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
