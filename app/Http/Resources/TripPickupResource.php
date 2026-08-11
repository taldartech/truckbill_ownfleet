<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripPickupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deliveredWeight = $this->deliveredWeightTon();
        $remainingWeight = $this->remainingWeightTon();
        $status = $this->deliveryStatus();

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'pickup_code' => $this->pickup_code,
            'sequence_no' => (int) $this->sequence_no,
            'station_id' => $this->station_id,
            'station_name' => $this->whenLoaded('station', fn () => $this->station?->name),
            'consignor' => $this->consignor,
            'from_address' => $this->from_address,
            'goods_name' => $this->goods_name,
            'no_of_units' => $this->no_of_units,
            'goods_value' => $this->goods_value,
            'ewaybill_no' => $this->ewaybill_no,
            'ewaybill_expiry' => $this->ewaybill_expiry?->format('Y-m-d'),
            'pickup_date' => $this->pickup_date?->format('Y-m-d'),
            'weight_ton' => (float) $this->weight_ton,
            'delivered_weight_ton' => $deliveredWeight,
            'remaining_weight_ton' => $remainingWeight,
            'delivery_status' => $status->value,
            'delivery_status_label' => $status->label(),
            'drops' => TripRouteLegResource::collection($this->whenLoaded('drops')),
        ];
    }
}
