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
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'trip_route_leg_id' => $this->trip_route_leg_id,
            'route_leg_sequence' => $this->whenLoaded('routeLeg', fn () => $this->routeLeg?->sequence_no),
            'expense_type' => $this->expense_type?->value ?? $this->expense_type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
        ];
    }
}
