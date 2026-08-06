<?php

namespace App\Http\Resources;

use App\Modules\TripManagement\Support\LoadClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripRouteLegResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $weightTon = (float) ($this->weight_ton ?? 0);
        $maxLoadTon = $this->relationLoaded('trip')
            ? (float) ($this->trip->max_load_ton ?? 0)
            : 0.0;
        $classification = LoadClassifier::classify($weightTon, $maxLoadTon);

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'from_station_id' => $this->from_station_id,
            'to_station_id' => $this->to_station_id,
            'from_city' => $this->from_city,
            'from_address' => $this->from_address,
            'to_city' => $this->to_city,
            'to_address' => $this->to_address,
            'load_classification' => $classification->toArray(),
            'sequence_no' => $this->sequence_no,
            'distance_km' => $this->distance_km,
            'ewaybill_no' => $this->ewaybill_no,
            'ewaybill_expiry' => $this->ewaybill_expiry?->format('Y-m-d'),
            'goods_value' => $this->goods_value,
            'goods_name' => $this->goods_name,
            'pickup_date' => $this->pickup_date?->format('Y-m-d'),
            'no_of_units' => $this->no_of_units,
            'consignor' => $this->consignor,
            'consignee' => $this->consignee,
            'freight_rate' => $this->freight_rate,
            'weight_ton' => $this->weight_ton,
            'expected_mileage_km_per_liter' => $this->expected_mileage_km_per_liter,
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
