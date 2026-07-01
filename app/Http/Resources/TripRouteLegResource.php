<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripRouteLegResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'from_station_id' => $this->from_station_id,
            'to_station_id' => $this->to_station_id,
            'from_location' => $this->from_location,
            'to_location' => $this->to_location,
            'from_city' => $this->from_city,
            'from_address' => $this->from_address,
            'to_city' => $this->to_city,
            'to_address' => $this->to_address,
            'route_type' => $this->route_type?->value ?? $this->route_type,
            'load_category' => (float) ($this->weight_ton ?? 0) > 0 ? 'LOADED' : 'EMPTY',
            'sequence_no' => $this->sequence_no,
            'distance_km' => $this->distance_km,
            'goods_name' => $this->goods_name,
            'consignor' => $this->consignor,
            'consignee' => $this->consignee,
            'freight_rate' => $this->freight_rate,
            'expected_mileage_km_per_liter' => $this->expected_mileage_km_per_liter,
            'weight_ton' => $this->weight_ton,
            'invoice_number' => $this->invoice_number,
            'lr_number' => $this->lr_number,
            'remarks' => $this->remarks,
            'freight_amount' => $this->freight_amount,
            'advance_received' => $this->advance_received,
            'balance_amount' => $this->balance_amount,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'billing_status' => $this->billing_status?->value ?? $this->billing_status,
        ];
    }
}
