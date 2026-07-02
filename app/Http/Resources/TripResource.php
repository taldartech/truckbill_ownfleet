<?php

namespace App\Http\Resources;

use App\Modules\TripManagement\Support\TripDieselPerformance;
use App\Support\ApiDateTime;
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
            'substitute_driver_id' => $this->substitute_driver_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'broker_id' => $this->broker_id,
            'broker_name' => $this->broker_name,
            'start_station_id' => $this->start_station_id,
            'start_station' => new StationResource($this->whenLoaded('startStation')),
            'trip_type' => $this->trip_type?->value ?? $this->trip_type,
            'status' => $this->status?->value ?? $this->status,
            'start_at' => ApiDateTime::toIso($this->start_at),
            'end_at' => ApiDateTime::toIso($this->end_at),
            'cancelled_at' => ApiDateTime::toIso($this->cancelled_at),
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'actual_arrival_at' => ApiDateTime::toIso($this->actual_arrival_at),
            'start_meter_reading_km' => $this->start_meter_reading_km,
            'end_meter_reading_km' => $this->end_meter_reading_km,
            'load_weight_ton' => $this->load_weight_ton,
            'planned_mileage_km_per_liter' => $this->planned_mileage_km_per_liter,
            'max_load_ton' => $this->max_load_ton,
            'planned_diesel_liters' => $this->planned_diesel_liters,
            'diesel_rate_per_liter' => $this->diesel_rate_per_liter,
            'total_distance_km' => $this->total_distance_km,
            'loaded_distance_km' => $this->loaded_distance_km,
            'half_loaded_distance_km' => $this->half_loaded_distance_km,
            'empty_distance_km' => $this->empty_distance_km,
            'total_expense_amount' => $this->total_expense_amount,
            'total_freight_amount' => $this->total_freight_amount,
            'profit_amount' => $this->profitAmount(),
            'diesel_performance' => $this->when(
                true,
                fn () => TripDieselPerformance::forTrip($this->resource),
            ),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'driver' => new DriverResource($this->whenLoaded('driver')),
            'substitute_driver' => new DriverResource($this->whenLoaded('substituteDriver')),
            'route_legs' => TripRouteLegResource::collection($this->whenLoaded('routeLegs')),
            'expenses' => TripExpenseResource::collection($this->whenLoaded('expenses')),
            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'changed_at' => ApiDateTime::toIso($history->changed_at),
                'remarks' => $history->remarks,
            ])),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
