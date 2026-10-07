<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typeCode = $this->expense_type instanceof \BackedEnum ? $this->expense_type->value : (string) $this->expense_type;
        $tenantId = (string) ($this->tenant_id ?? \App\Support\CurrentTenant::id());

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'trip_route_leg_id' => $this->trip_route_leg_id,
            'route_leg_sequence' => $this->routeLeg?->sequence_no,
            'expense_type' => $typeCode,
            'expense_type_label' => \App\Support\TripExpenseTypeDefaults::labelFor($tenantId, $typeCode),
            'other_type' => $this->other_type,
            'expense_date' => $this->expense_date?->toDateString(),
            'amount' => $this->amount,
            'fuel_liters' => $this->fuel_liters,
            'fuel_rate' => $this->fuel_rate,
            'fuel_odometer_km' => $this->fuel_odometer_km,
            'currency' => $this->currency,
            'notes' => $this->notes,
        ];
    }
}
